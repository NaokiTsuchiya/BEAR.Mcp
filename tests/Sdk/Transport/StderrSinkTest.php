<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use PHPUnit\Framework\TestCase;

use function fopen;
use function rewind;
use function stream_get_contents;

final class StderrSinkTest extends TestCase
{
    /** Regression: must write via a php://stderr stream, not the CLI-only STDERR constant */
    public function testWritesToTheGivenStream(): void
    {
        $stream = fopen('php://memory', 'w+');
        $this->assertIsResource($stream);

        (new StderrSink($stream))->write('stdout-leak-test');

        rewind($stream);
        $this->assertSame('stdout-leak-test', stream_get_contents($stream));
    }

    /** No sink to divert to — drop the leaked output rather than fataling the request */
    public function testSilentlyDropsOutputWhenTheStreamCannotBeOpened(): void
    {
        $this->expectNotToPerformAssertions();

        (new StderrSink('invalid-scheme://unreachable'))->write('dropped-leak-test');
    }
}
