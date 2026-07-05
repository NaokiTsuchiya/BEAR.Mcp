<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use NaokiTsuchiya\BEAR\Mcp\Sdk\ServerFactory;
use BEAR\Package\Injector;
use Mcp\Server\Transport\StdioTransport;

use function fwrite;
use function ob_start;

use const STDERR;

/**
 * Boot a BEAR.Sunday app and serve it over MCP stdio
 *
 * stdout discipline is a spec MUST: a single PHP notice on stdout corrupts
 * the JSON-RPC stream. The output-buffer guard diverts everything that
 * would reach stdout — echoes, notices, warnings, and fatal error display
 * (all of which pass through output buffering) — to stderr. The app's own
 * error policy (display_errors, log destinations) is deliberately left
 * untouched: protecting this binding's channel is our concern, error
 * policy is the app's. The transport itself writes protocol frames with
 * fwrite(STDOUT), which bypasses output buffering.
 */
final class McpBootstrap
{
    public function __invoke(string $appName, string $context, string $appDir): int
    {
        ob_start(static function (string $buffer): string {
            if ($buffer !== '') {
                fwrite(STDERR, $buffer);
            }

            return '';
        }, 1);

        $injector = Injector::getInstance($appName, $context, $appDir);
        $server = $injector->getInstance(ServerFactory::class)->create();

        return (int) $server->run(new StdioTransport());
    }
}
