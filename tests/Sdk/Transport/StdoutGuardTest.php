<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_get_contents;
use function fopen;
use function ini_get;
use function ini_set;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

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

    public function testFallsBackToErrorLogWhenTheStreamCannotBeOpened(): void
    {
        $previousLog = (string) ini_get('error_log');
        $logFile = (string) tempnam(sys_get_temp_dir(), 'stdout-guard-test-');
        ini_set('error_log', $logFile);

        try {
            (new StdoutGuard('invalid-scheme://unreachable'))(static function (): void {
                echo 'fallback-leak-test';
            });

            $this->assertStringContainsString('fallback-leak-test', (string) file_get_contents($logFile));
        } finally {
            ini_set('error_log', $previousLog);
            unlink($logFile);
        }
    }
}
