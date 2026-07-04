<?php

declare(strict_types=1);

namespace FakeVendor\DupProject\Resource\App;

use BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\ResourceObject;

class Alpha extends ResourceObject
{
    #[Mcp(name: 'same_name')]
    public function onGet(): static
    {
        $this->body = ['alpha' => true];

        return $this;
    }
}
