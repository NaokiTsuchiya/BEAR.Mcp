<?php

declare(strict_types=1);

namespace FakeVendor\ProjectionProject\Resource\App;

use BEAR\Resource\ResourceObject;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;

/** GET with arguments as Expose::Both: tool + resource template */
class Detail extends ResourceObject
{
    /**
     * Item detail
     *
     * @param int $id Item ID
     */
    #[Mcp(as: Expose::Both)]
    public function onGet(int $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }
}
