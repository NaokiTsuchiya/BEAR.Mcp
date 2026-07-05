<?php

declare(strict_types=1);

namespace FakeVendor\InvalidProject\Resource\App;

use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\ResourceObject;

class Broken extends ResourceObject
{
    /** Expose::Resource on a non-GET method must fail fast at scan time */
    #[Mcp(as: Expose::Resource)]
    public function onPost(string $title): static
    {
        $this->body = ['title' => $title];

        return $this;
    }
}
