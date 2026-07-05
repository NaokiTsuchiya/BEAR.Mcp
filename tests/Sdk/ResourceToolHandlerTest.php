<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk;

use NaokiTsuchiya\BEAR\Mcp\Map\AnnotationDeriver;
use NaokiTsuchiya\BEAR\Mcp\Map\ToolDescriptor;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Handler\ResourceToolHandler;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Handler\StructuredCallToolResult;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use Mcp\Schema\Content\TextContent;
use Mcp\Server\ClientGateway;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use PHPUnit\Framework\TestCase;
use stdClass;

use function assert;
use function json_decode;
use function json_encode;

use const INF;

final class ResourceToolHandlerTest extends TestCase
{
    private function handler(mixed $body, int $code = 200, array|null $outputSchema = null): ResourceToolHandler
    {
        $ro = new class extends ResourceObject {
            public function onGet(): static
            {
                return $this;
            }
        };
        $ro->code = $code;
        $ro->body = $body;

        $resource = $this->createStub(ResourceInterface::class);
        $resource->method('get')->willReturn($ro);

        return new ResourceToolHandler($resource, new ToolDescriptor(
            name: 'fake_get',
            uri: 'app://self/fake',
            verb: 'get',
            title: null,
            description: null,
            inputSchema: ['type' => 'object'],
            safety: (new AnnotationDeriver())('get'),
            outputSchema: $outputSchema,
        ));
    }

    private function gateway(): ClientGateway
    {
        return new ClientGateway(new Session(new InMemorySessionStore()));
    }

    public function testUnencodableBodyBecomesIsErrorNotEmptySuccess(): void
    {
        $result = $this->handler(['ratio' => INF])->execute([], $this->gateway());

        $this->assertTrue($result->isError);
        $content = $result->content[0];
        assert($content instanceof TextContent);
        $this->assertStringContainsString('JsonException', $content->text);
    }

    public function testOutputSchemaWithListBodyBecomesIsError(): void
    {
        $result = $this->handler([1, 2, 3], outputSchema: ['type' => 'object'])->execute([], $this->gateway());

        $this->assertTrue($result->isError);
        $content = $result->content[0];
        assert($content instanceof TextContent);
        $this->assertStringContainsString('does not conform to the declared outputSchema', $content->text);
    }

    public function testOutputSchemaWithEmptyBodySerializesEmptyObjectStructuredContent(): void
    {
        $result = $this->handler([], outputSchema: ['type' => 'object'])->execute([], $this->gateway());

        $this->assertInstanceOf(StructuredCallToolResult::class, $result);
        $this->assertFalse($result->isError);
        $wire = json_decode(json_encode($result), true);
        $this->assertSame([], $wire['structuredContent'], 'serialized as {} on the wire, not dropped');
        $this->assertInstanceOf(stdClass::class, $result->jsonSerialize()['structuredContent']);
        $content = $result->content[0];
        assert($content instanceof TextContent);
        $this->assertSame('{}', $content->text);
    }

    public function testOutputSchemaWithObjectBodyProvidesStructuredContent(): void
    {
        $result = $this->handler(['id' => 1], outputSchema: ['type' => 'object'])->execute([], $this->gateway());

        $this->assertFalse($result->isError);
        $this->assertSame(['id' => 1], $result->structuredContent);
    }

    public function testErrorCodeBecomesIsError(): void
    {
        $result = $this->handler(['message' => 'not found'], code: 404)->execute([], $this->gateway());

        $this->assertTrue($result->isError);
        $content = $result->content[0];
        assert($content instanceof TextContent);
        $this->assertStringStartsWith('404:', $content->text);
    }
}
