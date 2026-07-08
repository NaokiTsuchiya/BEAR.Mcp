<?php

declare(strict_types=1);

namespace FakeVendor\ProjectionProject\Resource\App;

use BEAR\Resource\ResourceObject;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;

/** A method-level as: overrides the class-level default field by field */
#[Mcp(as: Expose::Tool)]
class Wiki extends ResourceObject
{
    /**
     * Wiki page
     *
     * @param string $slug Page slug
     */
    #[Mcp(as: Expose::Both)]
    public function onGet(string $slug): static
    {
        $this->body = ['slug' => $slug];

        return $this;
    }
}
