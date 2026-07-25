<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Package\Injector;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Transport\McpRequestHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

use function array_map;
use function dirname;
use function explode;
use function fileperms;
use function is_array;
use function json_decode;
use function json_encode;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Streamable HTTP binding: PSR-7 round trips through the real fake-app stack
 *
 * No socket: requests go straight into the PSR-15 handler, exactly as a
 * PHP-FPM endpoint or a worker-mode pipeline would deliver them.
 */
final class HttpServerTest extends TestCase
{
    private McpRequestHandler $handler;
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $injector = Injector::getInstance(
            'FakeVendor\FakeProject',
            'app',
            dirname(__DIR__) . '/Fake/fake-app',
        );
        $this->handler = $injector->getInstance(McpRequestHandler::class);
        $this->psr17 = new Psr17Factory();
    }

    public function testProtocolRoundTripOverHttp(): void
    {
        // --- initialize opens a session
        $response = $this->post($this->initializeMessage(), sessionId: null);
        $this->assertSame(200, $response->getStatusCode());
        $sessionId = $response->getHeaderLine('Mcp-Session-Id');
        $this->assertNotSame('', $sessionId, 'server must issue a session id on initialize');
        $init = $this->decode($response);
        $this->assertSame('fake-app', $init['result']['serverInfo']['name']);
        $this->assertSame('2025-06-18', $init['result']['protocolVersion']);
        $this->assertSame(['completions' => [], 'resources' => [], 'tools' => []], $init['result']['capabilities']);

        $this->post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId);

        // --- tools/list sees the same surface as stdio
        $list = $this->decode($this->post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list', 'params' => []], $sessionId));
        $names = array_map(static fn (array $t): string => $t['name'], $list['result']['tools']);
        $this->assertSame(
            ['format_get', 'multi_get', 'multi_post', 'search_get', 'todo_archive', 'todo_get', 'todo_post', 'user_get'],
            $names,
        );

        // --- tools/call executes on the resource client
        $get = $this->decode($this->post([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'todo_get', 'arguments' => ['id' => 1]],
        ], $sessionId));
        $body = json_decode($get['result']['content'][0]['text'], true);
        $this->assertSame(['id' => 1, 'title' => 'Write tests', 'done' => false], $body);

        // --- resources/read reaches the same wiring as the stdio binding
        $read = $this->decode($this->post([
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'resources/read',
            'params' => ['uri' => 'app://self/todo?id=1'],
        ], $sessionId));
        $this->assertSame(
            ['id' => 1, 'title' => 'Write tests', 'done' => false],
            json_decode($read['result']['contents'][0]['text'], true),
        );

        // --- the session survives a brand-new handler + server (new FPM process)
        $freshInjector = Injector::getInstance('FakeVendor\FakeProject', 'app', dirname(__DIR__) . '/Fake/fake-app');
        $freshHandler = new McpRequestHandler(
            $freshInjector->getInstance(ServerFactory::class),
            $freshInjector->getInstance(AbstractAppMeta::class),
        );
        $listAgain = $this->decode($this->send($freshHandler, $this->postRequest(
            ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/list', 'params' => []],
            $sessionId,
        )));
        $this->assertArrayHasKey('tools', $listAgain['result'], 'file-backed session persists across processes');

        // --- DELETE terminates the session
        $delete = $this->send($this->handler, $this->request('DELETE', $sessionId));
        $this->assertLessThan(300, $delete->getStatusCode());
        $afterDelete = $this->send($this->handler, $this->postRequest(
            ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/list', 'params' => []],
            $sessionId,
        ));
        $this->assertSame(404, $afterDelete->getStatusCode(), 'terminated session id is gone');
    }

    public function testStdoutLeakDuringDispatchIsDivertedNotIntoTheHttpResponse(): void
    {
        // Unlike stdio (guarded once for the whole process by McpBootstrap), this
        // transport has no other output-buffer guard anywhere — McpRequestHandler
        // itself must divert a stray echo, or it would corrupt the response body
        $this->expectOutputString('');

        $response = $this->post($this->initializeMessage(), sessionId: null);
        $sessionId = $response->getHeaderLine('Mcp-Session-Id');
        $this->post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId);

        // Multi::onGet has a deliberate echo (see tests/Fake/fake-app), reused here
        $read = $this->decode($this->post([
            'jsonrpc' => '2.0',
            'id' => 21,
            'method' => 'resources/read',
            'params' => ['uri' => 'app://self/multi'],
        ], $sessionId));
        $this->assertSame(['multi' => 'get'], json_decode($read['result']['contents'][0]['text'], true));
    }

    public function testDnsRebindingProtectionRejectsForeignHost(): void
    {
        $request = $this->postRequest($this->initializeMessage(), null)
            ->withHeader('Host', 'evil.example.com');

        $this->assertSame(403, $this->send($this->handler, $request)->getStatusCode());
    }

    public function testMalformedSessionIdIsRejectedWithBadRequest(): void
    {
        // the SDK would throw an uncaught exception on Uuid::fromString('not-a-uuid')
        $post = $this->post(['jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/list', 'params' => []], 'not-a-uuid');
        $this->assertSame(400, $post->getStatusCode());
        $this->assertSame(-32600, $this->decode($post)['error']['code']);

        $delete = $this->send($this->handler, $this->request('DELETE', 'garbage!!'));
        $this->assertSame(400, $delete->getStatusCode());
    }

    public function testSessionDirectoryIsOwnerOnly(): void
    {
        $response = $this->post($this->initializeMessage(), sessionId: null);
        $this->assertSame(200, $response->getStatusCode());

        $dir = dirname(__DIR__) . '/Fake/fake-app/var/tmp/app/mcp-sessions';
        $this->assertDirectoryExists($dir);
        $this->assertSame('0700', substr(sprintf('%o', (int) fileperms($dir)), -4), 'session ids are file names — no enumeration by other users');
    }

    /** @return array<string, mixed> */
    private function initializeMessage(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'phpunit-http', 'version' => '1.0'],
            ],
        ];
    }

    /** @param array<string, mixed> $message */
    private function post(array $message, string|null $sessionId): ResponseInterface
    {
        return $this->send($this->handler, $this->postRequest($message, $sessionId));
    }

    private function send(McpRequestHandler $handler, ServerRequestInterface $request): ResponseInterface
    {
        return $handler->handle($request);
    }

    /** @param array<string, mixed> $message */
    private function postRequest(array $message, string|null $sessionId): ServerRequestInterface
    {
        return $this->request('POST', $sessionId)
            ->withBody($this->psr17->createStream(json_encode($message, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
    }

    private function request(string $method, string|null $sessionId): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest($method, 'http://localhost/mcp')
            ->withHeader('Host', 'localhost')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream');
        if ($sessionId !== null) {
            $request = $request->withHeader('Mcp-Session-Id', $sessionId);
        }

        return $request;
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        if (str_contains($response->getHeaderLine('Content-Type'), 'text/event-stream')) {
            $data = '';
            foreach (explode("\n", $body) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data .= trim(substr($line, 5));
                }
            }

            $body = $data;
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('Unexpected response body: ' . $body);
        }

        return $decoded;
    }
}
