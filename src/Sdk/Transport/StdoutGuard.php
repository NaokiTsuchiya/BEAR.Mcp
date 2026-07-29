<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use function ob_end_flush;
use function ob_start;

/**
 * Divert leaked output (echo, notice/warning/fatal display) to the given sink
 *
 * A transport-boundary concern, not a per-dispatch one: stdio needs exactly
 * one guard around the whole process (McpBootstrap installs it once, for
 * the process's entire lifetime); Streamable HTTP has no equivalent
 * process-wide guard, so McpRequestHandler wraps each request individually
 * — a stray echo there would otherwise land straight in the HTTP response
 * body, since the SDK writes that body to an explicit PSR-7 stream rather
 * than through output buffering.
 */
final class StdoutGuard
{
    public function __construct(private readonly OutputSink $sink)
    {
    }

    public function __invoke(callable $fn): mixed
    {
        ob_start(function (string $buffer): string {
            if ($buffer !== '') {
                $this->sink->write($buffer);
            }

            return '';
        }, 1);
        try {
            return $fn();
        } finally {
            ob_end_flush();
        }
    }
}
