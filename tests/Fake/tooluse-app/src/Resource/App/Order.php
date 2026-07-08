<?php

declare(strict_types=1);

namespace FakeVendor\ToolUseProject\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;

class Order extends ResourceObject
{
    /**
     * Get an order
     */
    public function onGet(int|null $id = null): static
    {
        $this->body = ['id' => $id];

        return $this;
    }

    /**
     * Place an order
     */
    #[Tool(confirm: true)]
    public function onPost(string $item, int $qty = 1): static
    {
        $this->code = 201;
        $this->body = ['item' => $item, 'qty' => $qty];

        return $this;
    }

    /**
     * Cancel an order
     */
    #[Tool(name: 'order_cancel')]
    public function onDelete(int $id): static
    {
        $this->body = ['cancelled' => $id];

        return $this;
    }
}
