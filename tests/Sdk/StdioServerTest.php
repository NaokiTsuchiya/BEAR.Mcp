<?php

declare(strict_types=1);

namespace BEAR\Mcp\Sdk;

use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_filter;
use function array_map;
use function dirname;
use function explode;
use function fclose;
use function feof;
use function fgets;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function is_array;
use function is_dir;
use function is_resource;
use function json_decode;
use function json_encode;
use function mkdir;
use function proc_close;
use function proc_open;
use function rmdir;
use function scandir;
use function stream_get_contents;
use function stream_select;
use function stream_set_blocking;
use function trim;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_BINARY;

/**
 * Protocol E2E: boot the fake app over real stdio and drive it with JSON-RPC
 *
 * Doubles as the stdout-discipline regression test: every line the server
 * writes to stdout must be valid JSON-RPC, even though a fake resource
 * deliberately echoes garbage during tools/call.
 */
final class StdioServerTest extends TestCase
{
    /** @var resource */
    private $process;

    /** @var array{0: resource, 1: resource, 2: resource} */
    private array $pipes;

    /** @var list<string> */
    private array $stdoutLines = [];

    private static function fakeAppDir(): string
    {
        return dirname(__DIR__) . '/Fake/fake-app';
    }

    protected function setUp(): void
    {
        self::removeDir(self::fakeAppDir() . '/var/tmp'); // no stale DI cache

        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__, 2) . '/bin/bear-mcp', 'FakeVendor\FakeProject', 'app', self::fakeAppDir()],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start bear-mcp');
        }

        $this->process = $process;
        $this->pipes = $pipes;
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
    }

    protected function tearDown(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($this->process);
    }

    public function testProtocolRoundTrip(): void
    {
        $init = $this->request(1, 'initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ]);
        $this->assertSame('fake-app', $init['result']['serverInfo']['name']);
        $this->assertSame('1.0.0', $init['result']['serverInfo']['version']);
        $this->assertSame('Fake BEAR.Sunday app for BEAR.Mcp tests.', $init['result']['instructions']);
        $this->assertSame('2025-06-18', $init['result']['protocolVersion'], 'spec MUST: echo the supported requested version');
        $this->assertSame(['tools' => []], $init['result']['capabilities'], 'declare only what this server supports');

        $this->notify('notifications/initialized');

        // --- tools/list: golden wire-format comparison
        $list = $this->request(2, 'tools/list');
        $this->assertGolden($list['result']['tools']);

        // --- tools/call: success
        $get = $this->request(3, 'tools/call', ['name' => 'todo_get', 'arguments' => ['id' => 1]]);
        $this->assertFalse($get['result']['isError'] ?? false, 'no error flag on success');
        $body = json_decode($get['result']['content'][0]['text'], true);
        $this->assertSame(['id' => 1, 'title' => 'Write tests', 'done' => false], $body);

        // --- tools/call: business error becomes isError (self-correction), not a protocol error
        $notFound = $this->request(4, 'tools/call', ['name' => 'todo_get', 'arguments' => ['id' => 999]]);
        $this->assertTrue($notFound['result']['isError']);
        $this->assertStringStartsWith('404:', $notFound['result']['content'][0]['text']);

        // --- tools/call: schema violation is rejected by the SDK before dispatch (-32602)
        $invalid = $this->request(5, 'tools/call', ['name' => 'todo_get', 'arguments' => ['id' => 'abc']]);
        $this->assertSame(-32602, $invalid['error']['code']);

        // --- tools/call: POST with a deliberate echo in the resource (stdout guard)
        $post = $this->request(6, 'tools/call', ['name' => 'todo_post', 'arguments' => ['title' => 'New task']]);
        $created = json_decode($post['result']['content'][0]['text'], true);
        $this->assertSame(['id' => 3, 'title' => 'New task', 'done' => false], $created);

        // --- tools/call: structuredContent when the resource declares an object response schema
        $user = $this->request(7, 'tools/call', ['name' => 'user_get', 'arguments' => []]);
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $user['result']['structuredContent']);

        // --- renamed + safety-overridden tool dispatches on the right verb
        $archive = $this->request(8, 'tools/call', ['name' => 'todo_archive', 'arguments' => ['id' => 1]]);
        $this->assertSame(['archived' => 1], json_decode($archive['result']['content'][0]['text'], true));

        // --- stdout discipline: every line the server wrote was valid JSON
        foreach ($this->stdoutLines as $line) {
            json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        // --- the echoed garbage was diverted to stderr, not stdout
        $stderr = (string) stream_get_contents($this->pipes[2]);
        $this->assertStringContainsString('stdout-leak-test', $stderr);
    }

    /** @param array<string, mixed> $tools */
    private function assertGolden(array $tools): void
    {
        $goldenFile = dirname(__DIR__) . '/golden/tools-list.json';
        $actual = json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        if (! file_exists($goldenFile)) {
            if (! is_dir(dirname($goldenFile))) {
                mkdir(dirname($goldenFile), 0777, true);
            }

            file_put_contents($goldenFile, $actual);
            $this->fail('Golden file created: ' . $goldenFile . ' — inspect it and re-run.');
        }

        $this->assertJsonStringEqualsJsonString((string) file_get_contents($goldenFile), $actual);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function request(int $id, string $method, array $params = []): array
    {
        $this->send(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
        $response = json_decode($this->readLine(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertSame($id, $response['id']);

        return $response;
    }

    private function notify(string $method): void
    {
        $this->send(['jsonrpc' => '2.0', 'method' => $method]);
    }

    /** @param array<string, mixed> $message */
    private function send(array $message): void
    {
        fwrite($this->pipes[0], json_encode($message, JSON_UNESCAPED_SLASHES) . "\n");
    }

    private function readLine(int $timeoutSeconds = 30): string
    {
        $deadline = \microtime(true) + $timeoutSeconds;
        while (\microtime(true) < $deadline) {
            $read = [$this->pipes[1]];
            $write = $except = [];
            if (stream_select($read, $write, $except, 0, 200_000) === false) {
                break;
            }

            while (! feof($this->pipes[1])) {
                $line = fgets($this->pipes[1]);
                if ($line === false) {
                    break;
                }

                if (trim($line) === '') {
                    continue;
                }

                $this->stdoutLines[] = trim($line);

                return trim($line);
            }
        }

        throw new RuntimeException('Timed out waiting for a server response. stderr: ' . (string) stream_get_contents($this->pipes[2]));
    }

    private static function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (array_filter(array_map('trim', (array) scandir($dir)), static fn ($f) => $f !== '' && $f !== '.' && $f !== '..') as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
