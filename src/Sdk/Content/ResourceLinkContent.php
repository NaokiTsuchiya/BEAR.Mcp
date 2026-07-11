<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Content;

use Mcp\Schema\Content\Content;

/**
 * A tool result content block that references a followable MCP resource
 *
 * The MCP spec defines this content type, but mcp/sdk v0.6.0 does not ship a
 * class for it (verified: no resource_link/ResourceLink hits anywhere under
 * vendor/mcp/sdk/src) — this fills that gap.
 */
final class ResourceLinkContent extends Content
{
    public function __construct(
        public readonly string $uri,
        public readonly string $name,
        public readonly string|null $title = null,
    ) {
        parent::__construct('resource_link');
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        $result = [
            'type' => 'resource_link',
            'uri' => $this->uri,
            'name' => $this->name,
        ];

        if ($this->title !== null) {
            $result['title'] = $this->title;
        }

        return $result;
    }
}
