<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use NaokiTsuchiya\BEAR\Mcp\Sdk\ServerFactory;
use BEAR\Package\Injector;
use Mcp\Server\Transport\StdioTransport;

/**
 * Boot a BEAR.Sunday app and serve it over MCP stdio
 *
 * stdout discipline is a spec MUST: a single PHP notice on stdout corrupts
 * the JSON-RPC stream. StdoutGuard diverts everything that would reach
 * stdout — echoes, notices, warnings, and fatal error display (all of which
 * pass through output buffering) — to stderr, for the whole process
 * lifetime. The app's own error policy (display_errors, log destinations)
 * is deliberately left untouched: protecting this binding's channel is our
 * concern, error policy is the app's. The transport itself writes protocol
 * frames with fwrite(STDOUT), which bypasses output buffering.
 */
final class McpBootstrap
{
    public function __construct(
        private readonly StdoutGuard $guard = new StdoutGuard(),
    ) {
    }

    public function __invoke(string $appName, string $context, string $appDir): int
    {
        return ($this->guard)(static function () use ($appName, $context, $appDir): int {
            $injector = Injector::getInstance($appName, $context, $appDir);
            $server = $injector->getInstance(ServerFactory::class)->create();

            return (int) $server->run(new StdioTransport());
        });
    }
}
