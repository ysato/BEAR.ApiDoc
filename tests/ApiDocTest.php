<?php
namespace BEAR\ApiDoc;

use Aura\Router\RouterFactory;
use BEAR\Resource\JsonRenderer;
use BEAR\Resource\Module\JsonSchemalModule;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;
use Ray\Di\Injector;

class ApiDocTest extends TestCase
{
    /**
     * @var ApiDoc
     */
    private $apiDoc;

    /**
     * @var ResourceInterface
     */
    private $resource;

    public function setUp()
    {
        $router = (new RouterFactory)->newInstance();
        $schemaDir = __DIR__ . '/Fake/schema';
        $classDir = __DIR__ . '/tmp';
        $this->resource = $resource = (new Injector(
            new JsonSchemalModule(
                $schemaDir,
                '',
                new ResourceModule('FakeVendor\FakeProject')
            ),
            $classDir
        ))->getInstance(ResourceInterface::class);
        $apiDoc = new ApiDoc($router);
        $apiDoc->setScehmaDir(__DIR__ . '/Fake/schema');
        $apiDoc->setResource($resource);
        $apiDoc->setRenderer(new JsonRenderer());
        $this->apiDoc = $apiDoc;
    }

    public function testRender()
    {
        $options = $this->resource->options->uri('app://self/user')()->view;
        $expected = '{
    "GET": {
        "request": {
            "parameters": {
                "age": []
            },
            "required": [
                "age"
            ]
        },
        "schema": {
            "type": "object",
            "properties": {
                "firstName": {
                    "type": "string",
                    "maxLength": 30,
                    "pattern": "[a-z\\\\d~+-]+"
                },
                "lastName": {
                    "type": "string",
                    "maxLength": 30,
                    "pattern": "[a-z\\\\d~+-]+"
                },
                "age": {
                    "type": [
                        "integer",
                        "null"
                    ]
                }
            },
            "required": [
                "firstName",
                "lastName",
                "age"
            ]
        }
    }
}
';
        $this->assertSame($expected, $options);
    }
}