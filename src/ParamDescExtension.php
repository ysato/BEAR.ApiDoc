<?php

namespace BEAR\ApiDoc;

use function implode;
use Koriym\Alps\AbstractAlps;
use Koriym\Alps\Alps;
use function sprintf;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ParamDescExtension extends AbstractExtension
{
    /**
     * @var AbstractAlps
     */
    private $alps;

    public function __construct(AbstractAlps $alps)
    {
        $this->alps = $alps;
    }

    public function getFilters() : array
    {
        return [
            new TwigFilter('param_desc', [$this, 'paramDesc'])
        ];
    }

    public function paramDesc(string $description = null, string $semanticName = null, $prop = null, $schema = null) : string
    {
        if ($description) {
            return $description;
        }
        if ($prop instanceof \stdClass) {
            $desc = $this->getDescription($prop, 'title');
            if ($desc) {
                return $desc;
            }
        }
        assert(is_string($semanticName));
        $name = lcfirst(strtr(ucwords(strtr($semanticName, ['_' => ' '])), [' ' => '']));
        $semantic = $this->alps->semantics[$name] ?? null;
        if ($semantic) {
            if ($semantic->def) {
                return sprintf('[%s](%s)', $semanticName, $semantic->def);
            }

            return $this->getDescription($semantic, 'name');
        }
        if ($semanticName[0] !== '_' && $this->alps instanceof Alps) {
            $this->errorLog((string) $semanticName, $schema);
        }

        return '';
    }

    private function getDescription(\stdClass $semantic, string $name) : string
    {
        $names = [];
        if ($semantic->{$name}) {
            $names[] = $semantic->{$name};
        }
        if ($semantic->doc->value) {
            $names[] = $semantic->doc->value;
        }

        return implode(', ', $names);
    }

    private function errorLog(string $semanticName, $schema) : void
    {
        $id = $schema['$id'] ?? '';
        $msg = $id ? sprintf('Missing semantic doc [%s] in [%s]', $semanticName, $id) : sprintf('Missing semantic doc [$%s]', $semanticName);
        error_log($msg);
    }
}
