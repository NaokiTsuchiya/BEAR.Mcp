<?php

declare(strict_types=1);

namespace BEAR\Mcp\Map;

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
    ) {
    }
}
