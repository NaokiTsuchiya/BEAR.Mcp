<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

/**
 * Immutable publication map: which resource methods are exposed to MCP
 *
 * The inverse of a router: instead of matching incoming paths, it publishes
 * declared URIs. Built once at boot.
 */
final readonly class McpMap
{
    /** @param list<ToolDescriptor> $tools */
    public function __construct(
        public array $tools,
    ) {
    }
}
