<?php

declare(strict_types=1);

namespace FakeVendor\ProjectionProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;

/** Enum in the #[JsonSchema(params:)] file must surface as completion candidates */
class Doc extends ResourceObject
{
    /**
     * Render a document
     *
     * @param string $format Output format
     */
    #[Mcp]
    #[JsonSchema(params: 'doc.get.json')]
    public function onGet(string $format = 'html'): static
    {
        $this->body = ['format' => $format];

        return $this;
    }
}
