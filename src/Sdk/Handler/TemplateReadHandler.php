<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Handler;

use NaokiTsuchiya\BEAR\Mcp\Map\TemplateDescriptor;
use BEAR\Resource\Exception\ResourceNotFoundException as BearResourceNotFoundException;
use BEAR\Resource\ResourceInterface;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Exception\ResourceReadException;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ResourceTemplateHandlerInterface;
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
 * Dispatch a resources/read onto the BEAR resource client for a resource template
 */
final class TemplateReadHandler implements ResourceTemplateHandlerInterface
{
    public function __construct(
        private readonly ResourceInterface $resource,
        private readonly TemplateDescriptor $descriptor,
    ) {
    }

    /** @param array<string, string> $variables */
    public function read(string $uri, array $variables, ClientGateway $gateway): mixed
    {
        // Per-call stdout guard: any echo/notice inside the resource would corrupt
        // the stdio protocol, so leaked output is diverted to stderr
        ob_start();
        try {
            return $this->call($uri, $variables);
        } finally {
            $leaked = ob_get_clean();
            if (is_string($leaked) && $leaked !== '') {
                fwrite(STDERR, $leaked);
            }
        }
    }

    /** @param array<string, string> $variables */
    private function call(string $uri, array $variables): string
    {
        try {
            $ro = $this->resource->get($this->descriptor->uri, $variables);

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
