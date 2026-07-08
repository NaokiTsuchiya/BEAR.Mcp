<?php

declare(strict_types=1);

namespace FakeVendor\ToolUseProject\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Exclude;

/** Class-level #[Exclude]: SchemaConverter returns nothing, the bridge must too */
#[Exclude]
class Retired extends ResourceObject
{
    /**
     * Retired endpoint
     */
    public function onGet(): static
    {
        $this->body = [];

        return $this;
    }
}
