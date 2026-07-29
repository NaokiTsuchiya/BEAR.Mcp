<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

/** Where StdoutGuard diverts output that would otherwise leak to stdout */
interface OutputSink
{
    public function write(string $data): void;
}
