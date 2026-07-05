<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Server;

/**
 * MCP server identity and instructions
 */
final readonly class McpConfig
{
    public function __construct(
        public string $name,
        public string $version = '0.1.0',
        public string|null $instructions = null,
    ) {
    }
}
