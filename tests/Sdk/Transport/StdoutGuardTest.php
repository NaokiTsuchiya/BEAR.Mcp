<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use PHPUnit\Framework\TestCase;

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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        (new StdoutGuard())(static function (): never {
            throw new \RuntimeException('boom');
        });
    }

    public function testGuardCanBeUsedAgainAfterClosing(): void
    {
        $guard = new StdoutGuard();

        $this->assertSame('first', $guard(static fn (): string => 'first'));
        $this->assertSame('second', $guard(static fn (): string => 'second'));
    }
}
