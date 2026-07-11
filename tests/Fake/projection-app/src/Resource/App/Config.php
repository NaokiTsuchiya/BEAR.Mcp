<?php

declare(strict_types=1);

namespace FakeVendor\ProjectionProject\Resource\App;

use BEAR\Resource\ResourceObject;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;

/** Argument-less GET as Expose::Resource: MCP resource only, no tool */
class Config extends ResourceObject
{
    /** Application configuration */
    #[Mcp(as: Expose::Resource, title: 'Config', mimeType: 'text/plain')]
    public function onGet(): static
    {
        $this->body = ['debug' => false];

        return $this;
    }
}
