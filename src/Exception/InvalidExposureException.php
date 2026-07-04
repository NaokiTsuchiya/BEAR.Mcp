<?php

declare(strict_types=1);

namespace BEAR\Mcp\Exception;

use LogicException;

/**
 * Expose::Resource / Expose::Both declared on a non-GET method
 */
final class InvalidExposureException extends LogicException
{
}
