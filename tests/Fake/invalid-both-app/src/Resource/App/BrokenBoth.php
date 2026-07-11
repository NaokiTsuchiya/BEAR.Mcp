<?php

declare(strict_types=1);

namespace FakeVendor\InvalidBothProject\Resource\App;

use BEAR\Resource\ResourceObject;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;

class BrokenBoth extends ResourceObject
{
    /** Expose::Both on a non-GET method must fail fast at scan time */
    #[Mcp(as: Expose::Both)]
    public function onPost(string $title): static
    {
        $this->body = ['title' => $title];

        return $this;
    }
}
