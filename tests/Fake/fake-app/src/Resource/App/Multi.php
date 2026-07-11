<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\ResourceObject;

/** Class-level name: is ignored — two verbs must get derived, distinct names */
#[Mcp(name: 'multi')]
class Multi extends ResourceObject
{
    public function onGet(): static
    {
        echo 'stdout-leak-test'; // exercises the per-call stdout guard on the resources/read path

        $this->body = ['multi' => 'get'];

        return $this;
    }

    public function onPost(): static
    {
        $this->body = ['multi' => 'post'];

        return $this;
    }
}
