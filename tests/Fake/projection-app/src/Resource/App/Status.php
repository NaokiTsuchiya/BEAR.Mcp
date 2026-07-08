<?php

declare(strict_types=1);

namespace FakeVendor\ProjectionProject\Resource\App;

use BEAR\Resource\ResourceObject;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;

/** GET as Expose::Tool: tool only, resource projection suppressed */
class Status extends ResourceObject
{
    /**
     * System status
     *
     * @param string $component Component name
     */
    #[Mcp(as: Expose::Tool)]
    public function onGet(string $component): static
    {
        $this->body = ['component' => $component, 'status' => 'ok'];

        return $this;
    }
}
