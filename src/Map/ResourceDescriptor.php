<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

/**
 * SDK-neutral description of one exposed MCP resource
 *
 * The projection of an argument-less GET method: the BEAR resource URI is
 * published verbatim as the MCP resource URI (the adapter is the identity
 * function).
 */
final readonly class ResourceDescriptor
{
    /** @param string $uri BEAR resource URI (app://self/config) */
    public function __construct(
        public string $uri,
        public string $name,
        public string|null $title,
        public string|null $description,
        public string $mimeType = 'application/json',
    ) {
    }
}
