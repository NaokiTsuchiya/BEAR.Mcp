<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\ResourceObject;

/**
 * Abstract resources are skipped by the scanner, and #[Mcp] on their methods
 * must NOT silently expose attribute-less subclasses (grep-able surface)
 */
abstract class AbstractItem extends ResourceObject
{
    /** Get an item */
    #[Mcp]
    public function onGet(int $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }
}
