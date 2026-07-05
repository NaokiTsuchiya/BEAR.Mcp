<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Attribute;

/**
 * How a resource method is projected onto MCP primitives
 */
enum Expose
{
    /** GET: Resource + Tool / other verbs: Tool */
    case Auto;

    /** GET only: MCP resource / resource template (no tool) */
    case Resource;

    /** Tool only */
    case Tool;

    /** GET only: both resource and tool */
    case Both;
}
