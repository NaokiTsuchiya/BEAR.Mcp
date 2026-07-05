<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use BEAR\AppMeta\AbstractAppMeta;
use Http\Discovery\Psr17FactoryDiscovery;
use Mcp\Server;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\Http\Middleware\ProtocolVersionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use NaokiTsuchiya\BEAR\Mcp\Sdk\ServerFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ray\Di\Di\Named;
use Symfony\Component\Uid\Uuid;

use function assert;
use function fclose;
use function flock;
use function fopen;
use function is_dir;
use function json_encode;
use function mkdir;

use const LOCK_EX;
use const LOCK_UN;

/**
 * Streamable HTTP binding as a PSR-15 handler
 *
 * Mount it on any PSR-15 stack (FrankenPHP worker, RoadRunner, a middleware
 * pipeline) or serve it from plain PHP-FPM via McpHttpEndpoint. One MCP
 * endpoint, POST/GET/DELETE/OPTIONS semantics are the SDK's responsibility.
 *
 * Requests carrying the same Mcp-Session-Id are serialized with a per-session
 * file lock: the SDK's session handling is a whole-blob read-modify-write, so
 * unsynchronized parallel requests on one session can misdeliver or drop
 * responses regardless of the store backend. The lock serializes per host —
 * multi-host deployments need sticky sessions (or accept the race).
 *
 * Authentication is NOT provided here: v0.x targets a trusted boundary.
 * Put a Bearer/OAuth middleware in front of this handler when exposing it.
 */
final class McpRequestHandler implements RequestHandlerInterface
{
    private Server|null $server = null;

    /**
     * @param list<string>|null $allowedHosts Host allowlist for DNS-rebinding
     *   protection. null = the SDK default (localhost only). Set your real
     *   hostname(s) via McpHttpModule when serving beyond localhost.
     */
    public function __construct(
        private readonly ServerFactory $factory,
        private readonly AbstractAppMeta $appMeta,
        #[Named('mcp_allowed_hosts')]
        private readonly array|null $allowedHosts = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $sessionId = $request->getHeaderLine('Mcp-Session-Id');
        if ($sessionId !== '' && ! Uuid::isValid($sessionId)) {
            // The SDK feeds the raw header to Uuid::fromString(), which throws on
            // malformed input (and quietly accepts any 16-byte string as binary).
            // Reject here: 400, not an uncaught exception.
            return $this->badRequest('Invalid Mcp-Session-Id header.');
        }

        // built once, reused across requests in worker mode; per-request in PHP-FPM
        $this->server ??= $this->factory->create();

        $middleware = $this->allowedHosts === null ? null : [
            new CorsMiddleware(),
            new DnsRebindingProtectionMiddleware($this->allowedHosts),
            new ProtocolVersionMiddleware(),
        ];

        $lock = $sessionId === '' ? null : $this->acquireSessionLock($sessionId);
        try {
            $response = $this->server->run(new StreamableHttpTransport($request, middleware: $middleware));
            assert($response instanceof ResponseInterface);

            return $response;
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * Blocking per-session lock; empty .lock files are left behind by design
     * (deleting them would race a waiter holding the old inode)
     *
     * @return resource|null null degrades to unserialized handling rather than failing the request
     */
    private function acquireSessionLock(string $sessionId)
    {
        $dir = $this->appMeta->tmpDir . '/mcp-sessions';
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return null;
        }

        $handle = @fopen($dir . '/' . $sessionId . '.lock', 'c');
        if ($handle === false) {
            return null;
        }

        flock($handle, LOCK_EX);

        return $handle;
    }

    private function badRequest(string $message): ResponseInterface
    {
        $response = Psr17FactoryDiscovery::findResponseFactory()
            ->createResponse(400)
            ->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode(
            ['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => $message]],
        ));

        return $response;
    }
}
