<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Handler;

use Mcp\Schema\Result\CallToolResult;
use stdClass;

/**
 * CallToolResult that always serializes structuredContent
 *
 * Tools declaring an outputSchema MUST provide structured results (spec).
 * The parent's jsonSerialize() drops falsy structuredContent, which silently
 * violates that MUST for an empty-object body — PHP cannot distinguish []
 * from {} so an empty object is serialized explicitly.
 */
final class StructuredCallToolResult extends CallToolResult
{
    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $result = parent::jsonSerialize();
        $result['structuredContent'] = $this->structuredContent === null || $this->structuredContent === []
            ? new stdClass()
            : $this->structuredContent;

        return $result;
    }
}
