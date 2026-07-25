<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Handler;

use NaokiTsuchiya\BEAR\Mcp\Map\TemplateDescriptor;
use BEAR\Package\Injector;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Exception\ResourceReadException;
use Mcp\Server\ClientGateway;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use PHPUnit\Framework\TestCase;

use function dirname;
use function json_decode;

final class TemplateReadHandlerTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance(
            'FakeVendor\FakeProject',
            'app',
            dirname(__DIR__, 2) . '/Fake/fake-app',
        );
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    private function gateway(): ClientGateway
    {
        return new ClientGateway(new Session(new InMemorySessionStore()));
    }

    private function descriptor(): TemplateDescriptor
    {
        return new TemplateDescriptor(
            uriTemplate: 'app://self/todo{?id}',
            uri: 'app://self/todo',
            name: 'todo',
            title: null,
            description: null,
            variables: ['id'],
        );
    }

    public function testSuccessReturnsEncodedBody(): void
    {
        $handler = new TemplateReadHandler($this->resource, $this->descriptor());

        $result = $handler->read('app://self/todo?id=1', ['id' => '1'], $this->gateway());

        $this->assertSame(['id' => 1, 'title' => 'Write tests', 'done' => false], json_decode($result, true));
    }

    public function testNotFoundMapsToResourceNotFoundException(): void
    {
        $handler = new TemplateReadHandler($this->resource, $this->descriptor());

        try {
            $handler->read('app://self/todo?id=99', ['id' => '99'], $this->gateway());
            $this->fail('expected ResourceNotFoundException');
        } catch (ResourceNotFoundException $e) {
            $this->assertSame('app://self/todo?id=99', $e->uri);
        }
    }

    public function testMissingRequiredVariableMapsToResourceReadException(): void
    {
        $handler = new TemplateReadHandler($this->resource, $this->descriptor());

        $this->expectException(ResourceReadException::class);
        $handler->read('app://self/todo', [], $this->gateway());
    }

    public function testServerErrorCodeMapsDirectlyToResourceReadException(): void
    {
        $ro = new class extends ResourceObject {
            public function onGet(): static
            {
                return $this;
            }
        };
        $ro->code = 500;
        $ro->body = ['message' => 'boom'];

        $resource = $this->createStub(ResourceInterface::class);
        $resource->method('get')->willReturn($ro);

        $handler = new TemplateReadHandler($resource, $this->descriptor());

        try {
            $handler->read('app://self/todo?id=1', ['id' => '1'], $this->gateway());
            $this->fail('expected ResourceReadException');
        } catch (ResourceReadException $e) {
            $this->assertStringStartsWith('500:', $e->getMessage());
        }
    }
}
