<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\ResourceObject;

/** Class-level name: is ignored — two verbs must get derived, distinct names */
#[Mcp(name: 'multi')]
class Multi extends ResourceObject
{
    public function onGet(): static
    {
        $this->body = ['multi' => 'get'];

        return $this;
    }

    public function onPost(): static
    {
        $this->body = ['multi' => 'post'];

        return $this;
    }
}
