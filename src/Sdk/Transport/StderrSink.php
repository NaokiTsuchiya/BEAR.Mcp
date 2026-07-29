<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use function fopen;
use function fwrite;

/**
 * Writes to php://stderr rather than the STDERR constant, which is defined
 * only under the CLI SAPI — McpRequestHandler wraps PHP-FPM requests through
 * StdoutGuard too, where the constant doesn't exist. The handle is safe to
 * reuse for the process's lifetime (worker mode) or to open fresh per
 * request (PHP-FPM); either way PHP closes it automatically.
 */
final class StderrSink implements OutputSink
{
    /** @var resource */
    private readonly mixed $stream;

    /** @param resource|null $stream An already-open stream (for tests); null opens php://stderr */
    public function __construct(mixed $stream = null)
    {
        $this->stream = $stream ?? fopen('php://stderr', 'wb');
    }

    public function write(string $data): void
    {
        fwrite($this->stream, $data);
    }
}
