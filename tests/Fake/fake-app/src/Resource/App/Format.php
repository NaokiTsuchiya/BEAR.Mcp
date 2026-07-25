<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;

/** Enum in the #[JsonSchema(params:)] file must surface as completion candidates */
class Format extends ResourceObject
{
    /**
     * Render output in a given format
     *
     * @param string $format Output format
     */
    #[Mcp]
    #[JsonSchema(params: 'format.get.json')]
    public function onGet(string $format = 'html'): static
    {
        $this->body = ['format' => $format];

        return $this;
    }
}
