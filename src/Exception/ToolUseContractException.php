<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Exception;

use LogicException;

/**
 * bear/tool-use no longer behaves as the interop bridge assumes
 *
 * Thrown when the bridge cannot pair SchemaConverter's output with the real
 * HTTP verbs — failing fast beats dispatching a tool to the wrong method.
 */
final class ToolUseContractException extends LogicException
{
}
