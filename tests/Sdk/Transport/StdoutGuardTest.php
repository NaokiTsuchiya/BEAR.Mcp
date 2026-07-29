<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use NaokiTsuchiya\BEAR\Mcp\Fake\FakeOutputSink;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StdoutGuardTest extends TestCase
{
    public function testReturnValuePassesThrough(): void
    {
        $result = (new StdoutGuard(new FakeOutputSink()))(static fn (): string => 'value');

        $this->assertSame('value', $result);
    }

    public function testEchoedOutputDoesNotReachTheCaller(): void
    {
        $this->expectOutputString('');

        (new StdoutGuard(new FakeOutputSink()))(static function (): void {
            echo 'leaked';
        });
    }

    public function testLeakedOutputIsDivertedToTheSink(): void
    {
        $sink = new FakeOutputSink();

        (new StdoutGuard($sink))(static function (): void {
            echo 'stdout-leak-test';
        });

        $this->assertSame(['stdout-leak-test'], $sink->written);
    }

    public function testExceptionsPropagateAfterTheGuardCloses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        (new StdoutGuard(new FakeOutputSink()))(static function (): never {
            throw new RuntimeException('boom');
        });
    }

    public function testGuardCanBeUsedAgainAfterClosing(): void
    {
        $guard = new StdoutGuard(new FakeOutputSink());

        $this->assertSame('first', $guard(static fn (): string => 'first'));
        $this->assertSame('second', $guard(static fn (): string => 'second'));
    }
}
