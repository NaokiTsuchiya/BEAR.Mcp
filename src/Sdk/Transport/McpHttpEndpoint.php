<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Transport;

use BEAR\Package\Injector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;

use function header;
use function http_response_code;
use function sprintf;

/**
 * Plain PHP-FPM entry point for the Streamable HTTP binding
 *
 * Drop into a public script and point your web server at it:
 *
 *     // public/mcp.php
 *     require dirname(__DIR__) . '/vendor/autoload.php';
 *     (new McpHttpEndpoint())('MyVendor\MyApp', 'prod-app', dirname(__DIR__));
 *
 * PHP-FPM's process-per-request model sidesteps the long-running-state
 * concerns of the stdio server; the DI container is restored from the
 * compiled cache on each request as in any BEAR.Sunday app.
 */
final class McpHttpEndpoint
{
    public function __invoke(string $appName, string $context, string $appDir): void
    {
        $injector = Injector::getInstance($appName, $context, $appDir);
        $handler = $injector->getInstance(McpRequestHandler::class);

        $psr17 = new Psr17Factory();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);

        $this->emit($handler->handle($creator->fromGlobals()));
    }

    private function emit(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (! $body->eof()) {
            echo $body->read(8192);
        }
    }
}
