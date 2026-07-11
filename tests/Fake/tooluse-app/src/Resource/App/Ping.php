<?php

declare(strict_types=1);

namespace FakeVendor\ToolUseProject\Resource\App;

use BEAR\Resource\ResourceObject;

class Ping extends ResourceObject
{
    /**
     * Liveness check
     */
    public function onGet(): static
    {
        $this->body = ['pong' => true];

        return $this;
    }
}
