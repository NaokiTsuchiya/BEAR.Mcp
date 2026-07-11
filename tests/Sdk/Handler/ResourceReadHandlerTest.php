<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Handler;

use NaokiTsuchiya\BEAR\Mcp\Map\ResourceDescriptor;
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

final class ResourceReadHandlerTest extends TestCase
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

    private function descriptor(string $uri, string $name): ResourceDescriptor
    {
        return new ResourceDescriptor($uri, $name, null, null);
    }

    public function testSuccessReturnsEncodedBody(): void
    {
        $handler = new ResourceReadHandler($this->resource, $this->descriptor('app://self/multi', 'multi'));

        $result = $handler->read('app://self/multi', $this->gateway());

        $this->assertSame(['multi' => 'get'], json_decode($result, true));
    }

    public function testStdoutLeakIsDivertedToStderrNotIntoTheReturnedContent(): void
    {
        $this->expectOutputString('');

        $handler = new ResourceReadHandler($this->resource, $this->descriptor('app://self/multi', 'multi'));
        $result = $handler->read('app://self/multi', $this->gateway());

        $this->assertSame(['multi' => 'get'], json_decode($result, true));
    }

    public function testOtherErrorCodeMapsToResourceReadException(): void
    {
        // Search::onGet(int|string $q, ...) has no default: an argument-less
        // dispatch (as a plain resource always is) throws BadRequestException
        $handler = new ResourceReadHandler($this->resource, $this->descriptor('app://self/search', 'search'));

        $this->expectException(ResourceReadException::class);
        $handler->read('app://self/search', $this->gateway());
    }

    public function testNotFoundCodeMapsToResourceNotFoundException(): void
    {
        $ro = new class extends ResourceObject {
            public function onGet(): static
            {
                return $this;
            }
        };
        $ro->code = 404;
        $ro->body = ['message' => 'not found'];

        $resource = $this->createStub(ResourceInterface::class);
        $resource->method('get')->willReturn($ro);

        $handler = new ResourceReadHandler($resource, $this->descriptor('app://self/gone', 'gone'));

        try {
            $handler->read('app://self/gone', $this->gateway());
            $this->fail('expected ResourceNotFoundException');
        } catch (ResourceNotFoundException $e) {
            $this->assertSame('app://self/gone', $e->uri);
        }
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

        $handler = new ResourceReadHandler($resource, $this->descriptor('app://self/broken', 'broken'));

        try {
            $handler->read('app://self/broken', $this->gateway());
            $this->fail('expected ResourceReadException');
        } catch (ResourceReadException $e) {
            $this->assertStringStartsWith('500:', $e->getMessage());
        }
    }
}
