<?php

declare(strict_types=1);

namespace BEAR\Mcp\Sdk\Transport;

use BEAR\Mcp\Sdk\ServerFactory;
use BEAR\Package\Injector;
use Mcp\Server\Transport\StdioTransport;

use function fwrite;
use function ini_set;
use function ob_start;

use const STDERR;

/**
 * Boot a BEAR.Sunday app and serve it over MCP stdio
 *
 * stdout discipline is a spec MUST: a single PHP notice on stdout corrupts
 * the JSON-RPC stream. display_errors goes to stderr and every buffered
 * output chunk is diverted to stderr. The transport itself writes protocol
 * frames with fwrite(STDOUT), which bypasses output buffering.
 */
final class McpBootstrap
{
    public function __invoke(string $appName, string $context, string $appDir): int
    {
        ini_set('display_errors', 'stderr');
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
