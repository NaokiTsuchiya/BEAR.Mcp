<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

use NaokiTsuchiya\BEAR\Mcp\Exception\DuplicateToolNameException;

use function sprintf;

/**
 * Immutable publication map: which resource methods are exposed to MCP
 *
 * The inverse of a router: instead of matching incoming paths, it publishes
 * declared URIs. Built once at boot.
 *
 * Tool-name uniqueness is a construction invariant, so merging tools from
 * another source (e.g. Interop\ToolUseBridge) into a new map fails fast on
 * collision with the same exception.
 */
final readonly class McpMap
{
    /**
     * @param list<ToolDescriptor>     $tools
     * @param list<ResourceDescriptor> $resources
     * @param list<TemplateDescriptor> $templates
     */
    public function __construct(
        public array $tools,
        public array $resources = [],
        public array $templates = [],
    ) {
        $this->assertUniqueToolNames($tools);
    }

    /** @param list<ToolDescriptor> $tools */
    private function assertUniqueToolNames(array $tools): void
    {
        $seen = [];
        foreach ($tools as $tool) {
            if (isset($seen[$tool->name])) {
                throw new DuplicateToolNameException(sprintf(
                    'Tool name "%s" is declared by both %s(on%s) and %s(on%s). Disambiguate with a method-level #[Mcp(name:)].',
                    $tool->name,
                    $seen[$tool->name]->uri,
                    $seen[$tool->name]->verb,
                    $tool->uri,
                    $tool->verb,
                ));
            }

            $seen[$tool->name] = $tool;
        }
    }
}
