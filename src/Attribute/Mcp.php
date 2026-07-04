<?php

declare(strict_types=1);

namespace BEAR\Mcp\Attribute;

use Attribute;

/**
 * Expose a resource method (or all methods of a resource class) to MCP
 *
 * Default-closed: methods without this attribute are never exposed.
 * A class-level attribute exposes all on* methods; a method-level attribute
 * overrides the class-level one field by field. Use #[McpExclude] to exclude
 * a single method from a class-level exposure.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Mcp
{
    /**
     * @param string|null $name        Tool name — method-level only, ignored on a class-level
     *                                 attribute (default: URI-path derived, e.g. "todo_get")
     * @param string|null $title       Human-readable title
     * @param string|null $description Tool description (default: phpdoc summary via OPTIONS metadata)
     * @param Expose|null $as          Projection (default: Auto)
     * @param string|null $mimeType    MIME type for resource projection (default: application/json)
     * @param bool|null   $destructive Override verb-derived destructiveHint (e.g. soft-delete DELETE)
     * @param bool|null   $idempotent  Override verb-derived idempotentHint
     * @param bool|null   $openWorld   Set true for resources that reach external systems
     */
    public function __construct(
        public string|null $name = null,
        public string|null $title = null,
        public string|null $description = null,
        public Expose|null $as = null,
        public string|null $mimeType = null,
        public bool|null $destructive = null,
        public bool|null $idempotent = null,
        public bool|null $openWorld = null,
    ) {
    }
}
