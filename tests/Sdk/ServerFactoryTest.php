<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk;

use NaokiTsuchiya\BEAR\Mcp\Map\AnnotationDeriver;
use NaokiTsuchiya\BEAR\Mcp\Map\LinkResolver;
use NaokiTsuchiya\BEAR\Mcp\Map\McpMap;
use NaokiTsuchiya\BEAR\Mcp\Map\McpMapFactoryInterface;
use NaokiTsuchiya\BEAR\Mcp\Map\ResourceDescriptor;
use NaokiTsuchiya\BEAR\Mcp\Map\TemplateDescriptor;
use NaokiTsuchiya\BEAR\Mcp\Map\ToolDescriptor;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Handler\InitializeHandler;
use NaokiTsuchiya\BEAR\Mcp\Server\McpConfig;
use BEAR\Resource\ResourceInterface;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server;
use Mcp\Server\Protocol;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

use function assert;

/**
 * Focused unit test for the tools/resources/completions capability gating
 * logic — E2E coverage (StdioServerTest/HttpServerTest) only ever exercises
 * the fake app's fixture, which happens to have both resources and
 * templates non-empty simultaneously, so it cannot distinguish || from &&.
 */
final class ServerFactoryTest extends TestCase
{
    public function testAllEmptyDeclaresNothing(): void
    {
        $capabilities = $this->capabilitiesFor(tools: [], resources: [], templates: []);

        $this->assertFalse($capabilities->tools);
        $this->assertFalse($capabilities->resources);
        $this->assertFalse($capabilities->completions);
    }

    public function testResourcesOnlyDeclaresResourcesNotCompletions(): void
    {
        $capabilities = $this->capabilitiesFor(
            tools: [],
            resources: [$this->resource()],
            templates: [],
        );

        $this->assertFalse($capabilities->tools);
        $this->assertTrue($capabilities->resources);
        $this->assertFalse($capabilities->completions);
    }

    public function testTemplatesOnlyDeclaresResourcesAndCompletions(): void
    {
        $capabilities = $this->capabilitiesFor(
            tools: [],
            resources: [],
            templates: [$this->template()],
        );

        $this->assertFalse($capabilities->tools);
        $this->assertTrue($capabilities->resources);
        $this->assertTrue($capabilities->completions);
    }

    public function testToolsOnlyDeclaresToolsNotResourcesOrCompletions(): void
    {
        $capabilities = $this->capabilitiesFor(
            tools: [$this->tool()],
            resources: [],
            templates: [],
        );

        $this->assertTrue($capabilities->tools);
        $this->assertFalse($capabilities->resources);
        $this->assertFalse($capabilities->completions);
    }

    /**
     * @param list<ToolDescriptor>     $tools
     * @param list<ResourceDescriptor> $resources
     * @param list<TemplateDescriptor> $templates
     */
    private function capabilitiesFor(array $tools, array $resources, array $templates): ServerCapabilities
    {
        $map = new McpMap($tools, $resources, $templates);
        $mapFactory = $this->createStub(McpMapFactoryInterface::class);
        $mapFactory->method('__invoke')->willReturn($map);

        $factory = new ServerFactory(
            new McpConfig('test-app'),
            $mapFactory,
            $this->createStub(ResourceInterface::class),
            new LinkResolver(),
        );

        $server = $factory->create();

        $protocolProp = new ReflectionProperty(Server::class, 'protocol');
        $protocol = $protocolProp->getValue($server);
        assert($protocol instanceof Protocol);

        $handlersProp = new ReflectionProperty(Protocol::class, 'requestHandlers');
        /** @var list<object> $handlers */
        $handlers = $handlersProp->getValue($protocol);

        foreach ($handlers as $handler) {
            if ($handler instanceof InitializeHandler) {
                $capabilitiesProp = new ReflectionProperty(InitializeHandler::class, 'capabilities');
                $capabilities = $capabilitiesProp->getValue($handler);
                assert($capabilities instanceof ServerCapabilities);

                return $capabilities;
            }
        }

        throw new RuntimeException('InitializeHandler not registered');
    }

    private function tool(): ToolDescriptor
    {
        return new ToolDescriptor(
            name: 'fake_get',
            uri: 'app://self/fake',
            verb: 'get',
            title: null,
            description: null,
            inputSchema: ['type' => 'object'],
            safety: (new AnnotationDeriver())('get'),
        );
    }

    private function resource(): ResourceDescriptor
    {
        return new ResourceDescriptor('app://self/fake', 'fake', null, null);
    }

    private function template(): TemplateDescriptor
    {
        return new TemplateDescriptor(
            uriTemplate: 'app://self/fake{?id}',
            uri: 'app://self/fake',
            name: 'fake',
            title: null,
            description: null,
            variables: ['id'],
        );
    }
}
