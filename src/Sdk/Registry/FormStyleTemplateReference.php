<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Registry;

use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Schema\ResourceTemplate;

use function array_flip;
use function array_intersect_key;
use function explode;
use function in_array;
use function parse_str;
use function strpos;
use function substr;

final class FormStyleTemplateReference extends ResourceTemplateReference
{
    private readonly string $base;

    /** @var list<string> */
    private readonly array $variableNames;

    /** @param array<string, class-string|object> $completionProviders */
    public function __construct(
        ResourceTemplate $resourceTemplate,
        callable|array|string $handler,
        array $completionProviders = [],
    ) {
        parent::__construct($resourceTemplate, $handler, $completionProviders);

        $uriTemplate = $resourceTemplate->uriTemplate;
        $queryStart = strpos($uriTemplate, '{?');
        $this->base = substr($uriTemplate, 0, $queryStart);
        $variableList = substr($uriTemplate, $queryStart + 2, -1);
        $this->variableNames = explode(',', $variableList);
    }

    public function matches(string $uri): bool
    {
        $parts = explode('?', $uri, 2);
        $base = $parts[0];
        $query = $parts[1] ?? '';

        if ($base !== $this->base) {
            return false;
        }

        if ($query === '') {
            return true;
        }

        parse_str($query, $queryVariables);
        foreach ($queryVariables as $name => $value) {
            if (! in_array($name, $this->variableNames, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function extractVariables(string $uri): array
    {
        $parts = explode('?', $uri, 2);
        if (! isset($parts[1])) {
            return [];
        }

        parse_str($parts[1], $queryVariables);

        return array_intersect_key($queryVariables, array_flip($this->variableNames));
    }
}
