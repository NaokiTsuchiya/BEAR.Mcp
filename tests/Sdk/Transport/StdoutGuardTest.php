<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fopen;
use function rewind;
use function stream_get_contents;

final class StdoutGuardTest extends TestCase
{
    public function testReturnValuePassesThrough(): void
    {
        $result = (new StdoutGuard())(static fn (): string => 'value');

        $this->assertSame('value', $result);
    }

    public function testEchoedOutputDoesNotReachTheCaller(): void
    {
        $this->expectOutputString('');

        (new StdoutGuard())(static function (): void {
            echo 'leaked';
        });
    }

    public function testExceptionsPropagateAfterTheGuardCloses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        (new StdoutGuard())(static function (): never {
            throw new RuntimeException('boom');
        });
    }

    public function testGuardCanBeUsedAgainAfterClosing(): void
    {
        $guard = new StdoutGuard();

        $this->assertSame('first', $guard(static fn (): string => 'first'));
        $this->assertSame('second', $guard(static fn (): string => 'second'));
    }

    /** Regression: the guard must divert via a php://stderr stream, not the CLI-only STDERR constant */
    public function testLeakedOutputIsWrittenToTheStreamNotTheStderrConstant(): void
    {
        $sink = fopen('php://memory', 'w+');
        $this->assertIsResource($sink);

        (new StdoutGuard($sink))(static function (): void {
            echo 'stdout-leak-test';
        });

        rewind($sink);
        $this->assertSame('stdout-leak-test', stream_get_contents($sink));
    }

    /** No sink to divert to — drop the leaked output rather than fataling the request */
    public function testSilentlyDropsLeakedOutputWhenTheStreamCannotBeOpened(): void
    {
        $this->expectOutputString('');

        $result = (new StdoutGuard('invalid-scheme://unreachable'))(static function (): string {
            echo 'dropped-leak-test';

            return 'value';
        });

        $this->assertSame('value', $result);
    }
}
