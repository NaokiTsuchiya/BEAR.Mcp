<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App\Todo;

use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\ResourceObject;

/**
 * The GET target of Todo::onGet's #[Link] href — registered as an MCP
 * resource template (not a tool) so the E2E suite can prove a resource_link
 * the todo_get tool emits is genuinely followable via resources/read
 */
#[Mcp(as: Expose::Resource)]
class Archive extends ResourceObject
{
    /** @param int $id Todo ID */
    public function onGet(int $id): static
    {
        $this->body = ['id' => $id, 'archived' => true];

        return $this;
    }
}
