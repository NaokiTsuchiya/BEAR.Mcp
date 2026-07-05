<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

/**
 * RFC 9110 risk vocabulary derived from the HTTP verb, expressed as MCP tool annotations
 */
final readonly class Safety
{
    public function __construct(
        public bool $readOnly,
        public bool $destructive,
        public bool $idempotent,
        public bool $openWorld = false,
    ) {
    }
}
