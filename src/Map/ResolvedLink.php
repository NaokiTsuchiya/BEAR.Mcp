<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

/**
 * SDK-neutral hyperlink: a #[Link] href resolved against a response body
 *
 * The neutral form of MCP's resource_link content block — the "next safe
 * transition" an agent can follow via resources/read.
 */
final readonly class ResolvedLink
{
    public function __construct(
        public string $rel,
        public string $uri,
        public string|null $title = null,
    ) {
    }
}
