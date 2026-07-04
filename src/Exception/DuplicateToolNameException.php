<?php

declare(strict_types=1);

namespace BEAR\Mcp\Exception;

use LogicException;

/**
 * Two exposed methods resolve to the same tool name
 */
final class DuplicateToolNameException extends LogicException
{
}
