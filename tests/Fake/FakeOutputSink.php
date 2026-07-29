<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Fake;

use NaokiTsuchiya\BEAR\Mcp\Sdk\Transport\OutputSink;

/** Records every write() call verbatim, for asserting what StdoutGuard diverted */
final class FakeOutputSink implements OutputSink
{
    /** @var list<string> */
    public array $written = [];

    public function write(string $data): void
    {
        $this->written[] = $data;
    }
}
