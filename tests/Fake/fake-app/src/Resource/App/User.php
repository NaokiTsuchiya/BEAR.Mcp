<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use NaokiTsuchiya\BEAR\Mcp\Attribute\McpExclude;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

#[Mcp(title: 'User')]
class User extends ResourceObject
{
    /**
     * Get a user by ID
     *
     * @param int $id User ID
     */
    #[JsonSchema(schema: 'user.json')]
    public function onGet(int $id = 1): static
    {
        $this->body = ['id' => $id, 'name' => 'Alice'];

        return $this;
    }

    /** Covered by the class-level attribute but explicitly excluded */
    #[McpExclude]
    public function onDelete(int $id): static
    {
        $this->body = ['deleted' => $id];

        return $this;
    }
}
