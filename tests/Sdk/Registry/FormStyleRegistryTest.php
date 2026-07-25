<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Registry;

use Mcp\Capability\Registry\ResourceReference;
use Mcp\Exception\InvalidCursorException;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use PHPUnit\Framework\TestCase;

final class FormStyleRegistryTest extends TestCase
{
    private FormStyleRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new FormStyleRegistry();
    }

    public function testPlainResourceExactMatchWinsOverOverlappingTemplateBase(): void
    {
        $this->registry->registerResourceTemplate(
            new ResourceTemplate('app://self/todo{?id}', 'todo'),
            static fn () => 'from-template',
        );
        $this->registry->registerResource(
            new ResourceDefinition('app://self/todo', 'todo-plain'),
            static fn () => 'from-plain-resource',
        );

        $reference = $this->registry->getResource('app://self/todo');

        $this->assertInstanceOf(ResourceReference::class, $reference);
        $this->assertSame('app://self/todo', $reference->resource->uri);
    }

    public function testTemplateMatchesRealClientUriWithQueryArgs(): void
    {
        $this->registry->registerResourceTemplate(
            new ResourceTemplate('app://self/search{?q,limit}', 'search'),
            static fn () => null,
        );

        $reference = $this->registry->getResource('app://self/search?q=foo&limit=5');

        $this->assertInstanceOf(FormStyleTemplateReference::class, $reference);
        $this->assertSame('app://self/search{?q,limit}', $reference->resourceTemplate->uriTemplate);
    }

    public function testLiteralUriTemplateKeyLookupIsUsedByCompletionCompleteHandler(): void
    {
        // CompletionCompleteHandler calls getResource() with the literal uriTemplate
        // string (containing raw '{'/'?'/'}' characters), not a resolved client URI.
        // This must resolve via an exact map lookup, never via matches() regex logic,
        // or completion/complete would regress the moment matches() changes shape.
        $this->registry->registerResourceTemplate(
            new ResourceTemplate('app://self/todo{?id}', 'todo'),
            static fn () => null,
        );

        $reference = $this->registry->getResource('app://self/todo{?id}');

        $this->assertInstanceOf(FormStyleTemplateReference::class, $reference);
        $this->assertSame('app://self/todo{?id}', $reference->resourceTemplate->uriTemplate);
    }

    public function testUnknownUriThrowsResourceNotFoundException(): void
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->registry->getResource('app://self/nowhere');
    }

    public function testResourceTemplateRoundTrip(): void
    {
        $template = new ResourceTemplate('app://self/todo{?id}', 'todo');
        $this->registry->registerResourceTemplate($template, static fn () => null);

        $this->assertTrue($this->registry->hasResourceTemplate('app://self/todo{?id}'));
        $this->assertSame(
            'app://self/todo{?id}',
            $this->registry->getResourceTemplate('app://self/todo{?id}')->resourceTemplate->uriTemplate,
        );

        $this->registry->unregisterResourceTemplate('app://self/todo{?id}');

        $this->assertFalse($this->registry->hasResourceTemplate('app://self/todo{?id}'));
        $this->expectException(ResourceNotFoundException::class);
        $this->registry->getResourceTemplate('app://self/todo{?id}');
    }

    public function testGetResourceTemplatesPaginatesAndRejectsGarbageCursor(): void
    {
        $this->registry->registerResourceTemplate(new ResourceTemplate('app://self/a{?id}', 'a'), static fn () => null);
        $this->registry->registerResourceTemplate(new ResourceTemplate('app://self/b{?id}', 'b'), static fn () => null);
        $this->registry->registerResourceTemplate(new ResourceTemplate('app://self/c{?id}', 'c'), static fn () => null);

        $page1 = $this->registry->getResourceTemplates(2);

        $this->assertCount(2, $page1->references);
        $this->assertNotNull($page1->nextCursor);

        $page2 = $this->registry->getResourceTemplates(2, $page1->nextCursor);

        $this->assertCount(1, $page2->references);
        $this->assertNull($page2->nextCursor);

        $this->expectException(InvalidCursorException::class);
        $this->registry->getResourceTemplates(2, 'not-a-valid-cursor');
    }

    public function testHasResourceTemplatesReflectsRegistrations(): void
    {
        $this->assertFalse($this->registry->hasResourceTemplates());

        $this->registry->registerResourceTemplate(new ResourceTemplate('app://self/a{?id}', 'a'), static fn () => null);

        $this->assertTrue($this->registry->hasResourceTemplates());
    }

    public function testDelegatedToolMethodsReachTheInnerRegistry(): void
    {
        $tool = new Tool('echo', null, ['type' => 'object', 'properties' => []], null, null);

        $this->registry->registerTool($tool, static fn () => 'ok');

        $this->assertTrue($this->registry->hasTool('echo'));
        $this->assertSame('echo', $this->registry->getTool('echo')->tool->name);
    }
}
