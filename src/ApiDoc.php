<?php

namespace BEAR\ApiDoc;

use function array_keys;
use Aura\Router\Map;
use Aura\Router\RouterContainer;
use BEAR\AppMeta\AbstractAppMeta;
use BEAR\AppMeta\Meta;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\TransferInterface;
use function file_get_contents;
use function json_decode;
use function json_encode;
use Koriym\Alps\AbstractAlps;
use LogicException;
use manuelodelain\Twig\Extension\LinkifyExtension;
use Ray\Di\Di\Inject;
use Ray\Di\Di\Named;
use function sprintf;
use function str_replace;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extensions\TextExtension;
use Twig\Loader\ArrayLoader;

class ApiDoc extends ResourceObject
{
    /**
     * @var ResourceInterface
     */
    private $resource;

    /**
     * Optional aura router
     *
     * @var null|RouterContainer
     */
    private $route;

    /**
     * @var string
     */
    private $schemaDir;

    /**
     * @var null|string
     */
    private $routerFile;

    /**
     * @var array|Map
     */
    private $map;

    /**
     * @var array
     */
    private $template = [];

    /**
     * @var string
     */
    private $appName;

    /**
     * @var string
     */
    private $ext;

    /**
     * @var AbstractAlps
     */
    private $alps;

    /**
     * @Named("schemaDir=json_schema_dir,routerContainer=router_container,routerFile=aura_router_file")
     */
    public function __construct(
        ResourceInterface $resource,
        string $schemaDir,
        AbstractTemplate $template,
        AbstractAlps $alps,
        $routerContainer,
        AbstractAppMeta $meta,
        string $routerFile = null
    ) {
        $this->resource = $resource;
        $this->route = $routerContainer;
        $this->schemaDir = $schemaDir;
        $this->routerFile = $routerFile;
        $this->map = $this->route instanceof RouterContainer ? $this->route->getMap() : [];
        $this->template = [
            'index' => $template->index,
            'base.html.twig' => $template->base,
            'home.html.twig' => $template->home,
            'uri.html.twig' => $template->uri,
            'rel.html.twig' => $template->rel,
            'allow.html.twig' => $template->allow,
            'request.html.twig' => $template->request,
            'embed.html.twig' => $template->embed,
            'link.html.twig' => $template->links,
            'definition.html.twig' => $template->definition,
            'schema.html.twig' => $template->shcemaTable,
        ];
        $this->ext = $template->ext;
        $index = $this->resource->get('app://self/index');
        $this->appName = $meta->name;
        $this->alps = $alps;
    }

    /**
     * @Inject
     */
    public function setRenderer(RenderInterface $renderer)
    {
        unset($renderer);
        $this->renderer = new class($this->template, $this->alps, $this->map) implements RenderInterface {
            /**
             * @var array
             */
            private $template;

            /**
             * @var AbstractAlps
             */
            private $alps;

            /**
             * @var iterable
             */
            private $map;

            public function __construct(array $template, AbstractAlps $alps, iterable $map)
            {
                $this->template = $template;
                $this->alps = $alps;
                $this->map = $map;
            }

            public function render(ResourceObject $ro)
            {
                $ro->headers['content-type'] = 'text/html; charset=utf-8';
                $twig = new Environment(new ArrayLoader($this->template), ['debug' => true]);
                $twig->addExtension(new DebugExtension);
                $twig->addExtension(new RefLinkExtension);
                $twig->addExtension(new LinkifyExtension);
                $twig->addExtension(new PropTypeExtension);
                $twig->addExtension(new ConstrainExtension);
                $twig->addExtension(new TextExtension);
                $twig->addExtension(new ParamDescExtension($this->alps));
                $twig->addExtension(new RevRouteExtension($this->map));
                $twig->addExtension(new AddNlExtension);
                $twig->addExtension(new MarkdownEscapeExtension);
                $twig->addExtension(new SnakeCaseExtension);
                $ro->view = $twig->render('index', (array) $ro->body);

                return $ro->view;
            }
        };

        return $this;
    }

    public function transfer(TransferInterface $responder, array $server)
    {
        if (! $responder instanceof FileResponder) {
            throw new LogicException(); // @codeCoverageIgnore
        }
        $uris = $this->getUri();
        $rels = $this->getRelDoc($uris);
        $responder->set($this->indexPage($uris), $this->schemaDir, $uris, $this->ext, $rels);

        return parent::transfer($responder, $server);
    }

    private function getRelDoc(array $uris) : array
    {
        $relDoc = [];
        foreach ($uris as $uri) {
            foreach ($uri->doc as $method => $docItem) {
                $links = $docItem['links'] ?? [];
                foreach ($links as $link) {
                    $relDoc[$link['rel']] = $link + ['link_from' => $uri->uriPath];
                }
            }
        }
        sort($relDoc);

        return $relDoc;
    }

    private function indexPage(array $uris) : array
    {
        $index = $this->resource->get('app://self/index')->body;
        if (! isset($index['_links'])) {
            throw new \RuntimeException('No _links in index');
        }
        list($curies, $links, $index) = $this->getRels($index);
        unset($index['_links']);
        $schemas = $this->getSchemas();
        $index += [
            'app_name' => $this->appName,
            'name' => $curies->name,
            'messages' => $index,
            'schemas' => $schemas,
            'uris' => $uris
        ];

        return $index;
    }

    private function getUri() : array
    {
        $uris = [];
        $meta = new Meta($this->appName, 'app');
        foreach ($meta->getGenerator('app') as $resMeta) {
            $path = $resMeta->uriPath;
            $routedUri = $this->getRoutedUri($path);
            $uri = 'app://self' . $path;
            $options = json_decode((string) $this->resource->options($uri)->view, true);
            $this->setMeta($options, $uri);
            $allow = array_keys($options);
            $uris[$routedUri] = new Uri($allow, $options, $path, $this->getUriFilePath($path));
        }

        return $uris;
    }

    private function getUriFilePath($path)
    {
        return sprintf('uri%s.%s', $path, $this->ext);
    }

    private function getRoutedUri(string $path) : string
    {
        foreach ($this->map as $route) {
            if ($route->name === $path) {
                return $route->path;
            }
        }

        return $path;
    }

    private function setMeta(array &$options, string $uri)
    {
        foreach ($options as &$option) {
            if (isset($option['schema'])) {
                $option['meta'] = new JsonSchema((string) json_encode($option['schema']), $uri);
            }
        }
    }

    private function getSchemas() : array
    {
        $schemas = [];
        foreach ((array) glob($this->schemaDir . '/*.json') as $json) {
            $schemas[] = new JsonSchema((string) file_get_contents((string) $json), (string) $json);
        }

        return $schemas;
    }

    private function getRels(array $index) : array
    {
        $curieLinks = $index['_links']['curies'];
        $curies = new Curies($curieLinks);
        $links = [];
        unset($index['_links']['curies'], $index['_links']['self']);
        foreach ($index['_links'] as $nameRel => $value) {
            $rel = (string) str_replace($curies->name . ':', '', $nameRel);
            $links[$rel] = new Curie($nameRel, $value, $curies);
        }

        return [$curies, $links, $index];
    }
}
