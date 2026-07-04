<?php

declare(strict_types=1);

namespace BEAR\Mcp\Schema;

/**
 * Self-description of one resource method, extracted from OPTIONS metadata
 */
final readonly class MethodMeta
{
    /**
     * @param array<string, mixed>      $inputSchema  JSON Schema (type: object)
     * @param array<string, mixed>|null $outputSchema JSON Schema (type: object) or null
     */
    public function __construct(
        public array $inputSchema,
        public array|null $outputSchema,
        public string|null $summary,
    ) {
    }
}
