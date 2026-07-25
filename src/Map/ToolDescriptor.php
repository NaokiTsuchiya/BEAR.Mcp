<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

use BEAR\Resource\Annotation\Link;

/**
 * SDK-neutral description of one exposed tool
 *
 * @psalm-type JsonSchemaArray = array<string, mixed>
 */
final readonly class ToolDescriptor
{
    /**
     * @param string                    $uri          BEAR resource URI (app://self/todo)
     * @param string                    $verb         get|post|put|patch|delete
     * @param array<string, mixed>      $inputSchema  JSON Schema (type: object)
     * @param array<string, mixed>|null $outputSchema JSON Schema (type: object) or null
     * @param list<Link>                $links        #[Link] attributes declared on the method
     */
    public function __construct(
        public string $name,
        public string $uri,
        public string $verb,
        public string|null $title,
        public string|null $description,
        public array $inputSchema,
        public Safety $safety,
        public array|null $outputSchema = null,
        public array $links = [],
    ) {
    }
}
