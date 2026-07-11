<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Schema;

/**
 * RFC 6570 form-style URI template derived from a method signature
 */
final readonly class UriTemplate
{
    /** @param list<string> $variables Template variable names in declaration order */
    public function __construct(
        public string $template,
        public array $variables,
    ) {
    }
}
