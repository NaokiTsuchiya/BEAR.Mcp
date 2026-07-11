<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Handler;

use NaokiTsuchiya\BEAR\Mcp\Map\ResourceDescriptor;
use BEAR\Resource\Exception\ResourceNotFoundException as BearResourceNotFoundException;
use BEAR\Resource\ResourceInterface;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Exception\ResourceReadException;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ResourceHandlerInterface;
use Throwable;

use function fwrite;
use function is_string;
use function json_encode;
use function ob_get_clean;
use function ob_start;
use function sprintf;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const STDERR;

/**
 * Dispatch a resources/read onto the BEAR resource client for a plain (argument-less) resource
 */
final class ResourceReadHandler implements ResourceHandlerInterface
{
    public function __construct(
        private readonly ResourceInterface $resource,
        private readonly ResourceDescriptor $descriptor,
    ) {
    }

    public function read(string $uri, ClientGateway $gateway): mixed
    {
        // Per-call stdout guard: any echo/notice inside the resource would corrupt
        // the stdio protocol, so leaked output is diverted to stderr
        ob_start();
        try {
            return $this->call($uri);
        } finally {
            $leaked = ob_get_clean();
            if (is_string($leaked) && $leaked !== '') {
                fwrite(STDERR, $leaked);
            }
        }
    }

    private function call(string $uri): string
    {
        try {
            $ro = $this->resource->get($this->descriptor->uri);

            if ($ro->code === 404) {
                throw new ResourceNotFoundException($uri);
            }

            if ($ro->code >= 400) {
                throw new ResourceReadException(sprintf('%d: %s', $ro->code, $this->encode($ro->body)));
            }

            return $this->encode($ro->body);
        } catch (BearResourceNotFoundException) {
            throw new ResourceNotFoundException($uri);
        } catch (ResourceNotFoundException | ResourceReadException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ResourceReadException($e::class . ': ' . $e->getMessage());
        }
    }

    private function encode(mixed $body): string
    {
        return json_encode(
            $body,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
