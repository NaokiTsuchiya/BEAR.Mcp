<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

/** Wraps a callable, diverting any output it produces away from the caller's channel */
interface OutputGuard
{
    public function __invoke(callable $fn): mixed;
}
