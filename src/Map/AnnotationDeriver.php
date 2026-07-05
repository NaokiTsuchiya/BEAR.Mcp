<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

/**
 * Derive tool safety annotations from the HTTP verb (RFC 9110 semantics)
 *
 * openWorldHint defaults to false: the application is a closed domain,
 * the inverse of the MCP spec's worst-case default for unannotated tools.
 */
final class AnnotationDeriver
{
    /** @var array<string, array{0: bool, 1: bool, 2: bool}> verb => [readOnly, destructive, idempotent] */
    private const VERB_SAFETY = [
        'get' => [true, false, true],
        'post' => [false, false, false],
        'put' => [false, true, true],
        'patch' => [false, true, false],
        'delete' => [false, true, true],
    ];

    public function __invoke(
        string $verb,
        bool|null $destructive = null,
        bool|null $idempotent = null,
        bool|null $openWorld = null,
    ): Safety {
        [$readOnly, $verbDestructive, $verbIdempotent] = self::VERB_SAFETY[$verb];

        return new Safety(
            readOnly: $readOnly,
            destructive: $destructive ?? $verbDestructive,
            idempotent: $idempotent ?? $verbIdempotent,
            openWorld: $openWorld ?? false,
        );
    }
}
