<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Exception;

use LogicException;

/**
 * Two exposed methods resolve to the same tool name
 */
final class DuplicateToolNameException extends LogicException
{
}
