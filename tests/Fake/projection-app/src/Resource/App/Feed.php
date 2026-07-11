<?php

declare(strict_types=1);

namespace FakeVendor\ProjectionProject\Resource\App;

use BEAR\Resource\ResourceObject;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;

/** Class-level as: Expose::Tool must suppress the resource projection class-wide */
#[Mcp(as: Expose::Tool)]
class Feed extends ResourceObject
{
    /**
     * Feed entries
     *
     * @param int $page Page number
     */
    public function onGet(int $page = 1): static
    {
        $this->body = ['page' => $page];

        return $this;
    }
}
