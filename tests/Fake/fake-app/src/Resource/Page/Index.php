<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\Page;

use BEAR\Resource\ResourceObject;

/** No #[Mcp] attribute: never exposed (default-closed) */
class Index extends ResourceObject
{
    public function onGet(): static
    {
        $this->body = ['greeting' => 'Hello'];

        return $this;
    }
}
