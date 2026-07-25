<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use function fopen;
use function fwrite;
use function is_string;
use function ob_end_flush;
use function ob_start;

/**
 * Divert leaked output (echo, notice/warning/fatal display) to stderr
 *
 * A transport-boundary concern, not a per-dispatch one: stdio needs exactly
 * one guard around the whole process (McpBootstrap installs it once, for
 * the process's entire lifetime); Streamable HTTP has no equivalent
 * process-wide guard, so McpRequestHandler wraps each request individually
 * — a stray echo there would otherwise land straight in the HTTP response
 * body, since the SDK writes that body to an explicit PSR-7 stream rather
 * than through output buffering.
 *
 * Opens its sink via php://stderr rather than the STDERR constant, which is
 * defined only under the CLI SAPI — McpRequestHandler wraps requests under
 * PHP-FPM too, where the constant doesn't exist. If the stream can't be
 * opened, leaked output is dropped rather than fataling the request. The
 * handle is safe to reuse for the process's lifetime (worker mode) or to
 * open fresh per request (PHP-FPM); either way PHP closes it automatically.
 */
final class StdoutGuard
{
    /** @var resource|null */
    private readonly mixed $stream;

    /** @param string|resource $target A stream URI to open, or an already-open stream (for tests) */
    public function __construct(mixed $target = 'php://stderr')
    {
        $handle = is_string($target) ? @fopen($target, 'wb') : $target;
        $this->stream = $handle === false ? null : $handle;
    }

    public function __invoke(callable $fn): mixed
    {
        ob_start(function (string $buffer): string {
            if ($buffer !== '' && $this->stream !== null) {
                fwrite($this->stream, $buffer);
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
