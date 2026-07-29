<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use function fopen;
use function fwrite;
use function is_string;

/**
 * Writes to php://stderr rather than the STDERR constant, which is defined
 * only under the CLI SAPI — McpRequestHandler wraps PHP-FPM requests through
 * StdoutGuard too, where the constant doesn't exist. If the stream can't be
 * opened, writes are silently dropped rather than fataling the request. The
 * handle is safe to reuse for the process's lifetime (worker mode) or to
 * open fresh per request (PHP-FPM); either way PHP closes it automatically.
 */
final class StderrSink implements OutputSink
{
    /** @var resource|null */
    private readonly mixed $stream;

    /** @param string|resource $target A stream URI to open, or an already-open stream (for tests) */
    public function __construct(mixed $target = 'php://stderr')
    {
        $handle = is_string($target) ? @fopen($target, 'wb') : $target;
        $this->stream = $handle === false ? null : $handle;
    }

    public function write(string $data): void
    {
        if ($this->stream !== null) {
            fwrite($this->stream, $data);
        }
    }
}
