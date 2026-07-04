<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\ResourceObject;

/** Union-typed parameter: OptionsMethods cannot reflect it, fallback derivation must */
class Search extends ResourceObject
{
    #[Mcp]
    public function onGet(int|string $q, int $limit = 10): static
    {
        $this->body = ['q' => $q, 'limit' => $limit];

        return $this;
    }
}
