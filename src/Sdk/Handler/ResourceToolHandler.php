<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Handler;

use NaokiTsuchiya\BEAR\Mcp\Map\ToolDescriptor;
use BEAR\Resource\Exception\BadRequestException;
use BEAR\Resource\ResourceInterface;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\ClientGateway;
use Mcp\Server\Handler\ToolHandlerInterface;
use Throwable;

use function array_is_list;
use function fwrite;
use function get_debug_type;
use function is_array;
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
 * Dispatch a tools/call onto the BEAR resource client
 *
 * Business and validation errors become isError results (self-correction
 * material for the LLM), never protocol errors. The response body is used
 * directly without going through a renderer, as in BEAR.Cli.
 */
final class ResourceToolHandler implements ToolHandlerInterface
{
    public function __construct(
        private readonly ResourceInterface $resource,
        private readonly ToolDescriptor $tool,
    ) {
    }

    public function execute(array $arguments, ClientGateway $gateway): CallToolResult
    {
        // Per-call stdout guard: any echo/notice inside the resource would corrupt
        // the stdio protocol, so leaked output is diverted to stderr
        ob_start();
        try {
            return $this->call($arguments);
        } finally {
            $leaked = ob_get_clean();
            if (is_string($leaked) && $leaked !== '') {
                fwrite(STDERR, $leaked);
            }
        }
    }

    /** @param array<string, mixed> $arguments */
    private function call(array $arguments): CallToolResult
    {
        try {
            $ro = match ($this->tool->verb) {
                'get' => $this->resource->get($this->tool->uri, $arguments),
                'post' => $this->resource->post($this->tool->uri, $arguments),
                'put' => $this->resource->put($this->tool->uri, $arguments),
                'patch' => $this->resource->patch($this->tool->uri, $arguments),
                'delete' => $this->resource->delete($this->tool->uri, $arguments),
            };

            if ($ro->code >= 400) {
                return $this->error(sprintf('%d: %s', $ro->code, $this->encode($ro->body)));
            }

            return $this->success($ro->body);
        } catch (BadRequestException $e) {
            return $this->error(sprintf('400: %s. Check argument types against inputSchema.', $e->getMessage()));
        } catch (Throwable $e) {
            return $this->error($e::class . ': ' . $e->getMessage());
        }
    }

    private function success(mixed $body): CallToolResult
    {
        if ($this->tool->outputSchema === null) {
            return new CallToolResult(content: [new TextContent($this->encode($body))]);
        }

        // Spec MUST: a tool declaring outputSchema provides conforming structuredContent
        if (! is_array($body) || ($body !== [] && array_is_list($body))) {
            return $this->error(sprintf(
                'Response does not conform to the declared outputSchema (expected object, got %s)',
                get_debug_type($body),
            ));
        }

        return new StructuredCallToolResult(
            content: [new TextContent($body === [] ? '{}' : $this->encode($body))],
            structuredContent: $body,
        );
    }

    private function encode(mixed $body): string
    {
        return json_encode(
            $body,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }

    private function error(string $message): CallToolResult
    {
        return CallToolResult::error([new TextContent($message)]);
    }
}
