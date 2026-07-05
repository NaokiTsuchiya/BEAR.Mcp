<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Attribute;

use Attribute;

/**
 * Exclude a method from a class-level #[Mcp] exposure
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class McpExclude
{
}
