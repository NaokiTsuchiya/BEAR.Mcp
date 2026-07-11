<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

use BEAR\AppMeta\Meta;
use NaokiTsuchiya\BEAR\Mcp\Exception\DuplicateToolNameException;
use NaokiTsuchiya\BEAR\Mcp\Exception\InvalidExposureException;
use NaokiTsuchiya\BEAR\Mcp\Schema\InputSchemaFactory;
use NaokiTsuchiya\BEAR\Mcp\Schema\UriTemplateFactory;
use BEAR\Resource\OptionsMethods;
use PHPUnit\Framework\TestCase;

use function array_map;
use function dirname;

final class AttributeMcpMapFactoryTest extends TestCase
{
    private function factory(string $appName, string $appDir): AttributeMcpMapFactory
    {
        return new AttributeMcpMapFactory(
            new Meta($appName, 'app', $appDir),
            new InputSchemaFactory(
                new OptionsMethods($appDir . '/var/json_schema'),
                $appDir . '/var/json_validate',
            ),
            new AnnotationDeriver(),
            new UriTemplateFactory(),
        );
    }

    private function projectionAppMap(): McpMap
    {
        return $this->factory('FakeVendor\ProjectionProject', dirname(__DIR__) . '/Fake/projection-app')();
    }

    private function fakeAppMap(): McpMap
    {
        return $this->factory('FakeVendor\FakeProject', dirname(__DIR__) . '/Fake/fake-app')();
    }

    private function tool(string $name): ToolDescriptor
    {
        foreach ($this->fakeAppMap()->tools as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }

        $this->fail('No such tool in map: ' . $name);
    }

    public function testMapContainsExactlyTheDeclaredSurface(): void
    {
        $names = array_map(static fn (ToolDescriptor $t): string => $t->name, $this->fakeAppMap()->tools);

        // Sorted, and nothing else. Notably absent:
        // - todo_put (no attribute), user_delete (#[McpExclude]), Page\Index (no attribute)
        // - abstract_item_get (abstract classes are not dispatchable)
        // - item_get (Item inherits AbstractItem's #[Mcp] onGet but carries no attribute
        //   itself — exposure never propagates through inheritance)
        $this->assertSame(
            ['format_get', 'multi_get', 'multi_post', 'search_get', 'todo_archive', 'todo_get', 'todo_post', 'user_get'],
            $names,
        );
    }

    public function testGetToolDerivation(): void
    {
        $todoGet = $this->tool('todo_get');

        $this->assertSame('app://self/todo', $todoGet->uri);
        $this->assertSame('get', $todoGet->verb);
        $this->assertSame('Get a todo by ID', $todoGet->description, 'phpdoc summary via OPTIONS metadata');
        $this->assertTrue($todoGet->safety->readOnly);
        $this->assertFalse($todoGet->safety->destructive);
        $this->assertTrue($todoGet->safety->idempotent);
        $this->assertNotNull($todoGet->outputSchema, '#[JsonSchema(schema:)] with object root');
    }

    public function testGetToolCollectsLinkAttributes(): void
    {
        $todoGet = $this->tool('todo_get');

        $this->assertCount(2, $todoGet->links);
        $this->assertSame('archive', $todoGet->links[0]->rel);
        $this->assertSame('/todo{?id}', $todoGet->links[0]->href);
        $this->assertSame('delete', $todoGet->links[0]->method);
        $this->assertSame('archive', $todoGet->links[1]->rel);
        $this->assertSame('app://self/todo/archive?id={id}', $todoGet->links[1]->href);
        $this->assertSame('get', $todoGet->links[1]->method);
    }

    public function testParamsFileWinsOverReflection(): void
    {
        $schema = $this->tool('todo_get')->inputSchema;

        $this->assertSame('object', $schema['type']);
        $this->assertSame('integer', $schema['properties']['id']['type']);
        $this->assertSame(
            'Todo ID (from validation schema)',
            $schema['properties']['id']['description'],
            'the #[JsonSchema(params:)] file wins over the phpdoc @param description',
        );
        $this->assertSame(1, $schema['properties']['id']['minimum'], 'constraint only the file knows');
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(['id'], $schema['required']);
    }

    public function testReflectionDerivedSchemaWithDefaults(): void
    {
        $schema = $this->tool('todo_post')->inputSchema;

        $this->assertSame('string', $schema['properties']['title']['type']);
        $this->assertSame('Todo title', $schema['properties']['title']['description'], 'phpdoc @param');
        $this->assertSame('boolean', $schema['properties']['done']['type']);
        $this->assertFalse($schema['properties']['done']['default']);
        $this->assertSame(['title'], $schema['required'], 'parameters with defaults are not required');
    }

    public function testNameAndSafetyOverrides(): void
    {
        $todoArchive = $this->tool('todo_archive');

        $this->assertSame('delete', $todoArchive->verb);
        $this->assertFalse($todoArchive->safety->destructive, 'soft delete: destructive overridden to false');
        $this->assertTrue($todoArchive->safety->idempotent, 'verb-derived value kept');
    }

    public function testClassLevelAttributeAndOutputSchema(): void
    {
        $userGet = $this->tool('user_get');

        $this->assertSame('User', $userGet->title, 'class-level #[Mcp(title:)]');
        $this->assertSame('Get a user by ID', $userGet->description);
        $this->assertSame([], $userGet->inputSchema['required'] ?? [], 'id has a default value');
        $this->assertNotNull($userGet->outputSchema);
        $this->assertSame('object', $userGet->outputSchema['type']);
    }

    public function testClassLevelNameIsIgnoredAndVerbsGetDerivedNames(): void
    {
        // Multi carries class-level #[Mcp(name: 'multi')] with two verbs: inheriting
        // the name would collide; both tools must get URI-derived names instead
        $this->assertSame('get', $this->tool('multi_get')->verb);
        $this->assertSame('post', $this->tool('multi_post')->verb);
    }

    public function testUnionTypedParameterFallsBackToReflectionOnlyDerivation(): void
    {
        $schema = $this->tool('search_get')->inputSchema;

        $this->assertSame(['integer', 'string'], $schema['properties']['q']['type']);
        $this->assertSame('integer', $schema['properties']['limit']['type']);
        $this->assertSame(10, $schema['properties']['limit']['default']);
        $this->assertSame(['q'], $schema['required']);
    }

    public function testFakeAppGetMethodsProjectAsResourcesAndTemplates(): void
    {
        $map = $this->fakeAppMap();

        // Every exposed GET projects twice under Expose::Auto; only Multi::onGet
        // is argument-less, so it is the sole plain resource
        $this->assertSame(
            ['app://self/multi'],
            array_map(static fn (ResourceDescriptor $r): string => $r->uri, $map->resources),
        );
        $this->assertSame(
            [
                'app://self/format{?format}',
                'app://self/search{?q,limit}',
                'app://self/todo/archive{?id}',
                'app://self/todo{?id}',
                'app://self/user{?id}',
            ],
            array_map(static fn (TemplateDescriptor $t): string => $t->uriTemplate, $map->templates),
        );
    }

    private function template(McpMap $map, string $uriTemplate): TemplateDescriptor
    {
        foreach ($map->templates as $template) {
            if ($template->uriTemplate === $uriTemplate) {
                return $template;
            }
        }

        $this->fail('No such template in map: ' . $uriTemplate);
    }

    public function testTemplateDerivation(): void
    {
        $todo = $this->template($this->fakeAppMap(), 'app://self/todo{?id}');

        $this->assertSame('app://self/todo', $todo->uri, 'plain BEAR uri, distinct from the expanded uriTemplate');
        $this->assertSame('todo', $todo->name, 'verb-less path form: the URI is the identity');
        $this->assertSame(['id'], $todo->variables);
        $this->assertSame('Get a todo by ID', $todo->description);
        $this->assertSame('application/json', $todo->mimeType);
        $this->assertSame([], $todo->completions, 'todo.get.json declares no enum');
    }

    public function testResourceDerivation(): void
    {
        $multi = $this->fakeAppMap()->resources[0];

        $this->assertSame('app://self/multi', $multi->uri);
        $this->assertSame('multi', $multi->name);
        $this->assertSame('application/json', $multi->mimeType);
    }

    public function testExposeResourceSuppressesTheTool(): void
    {
        $map = $this->projectionAppMap();

        $names = array_map(static fn (ToolDescriptor $t): string => $t->name, $map->tools);
        $this->assertSame(
            ['detail_get', 'doc_get', 'feed_get', 'session_get', 'status_get', 'wiki_get'],
            $names,
            'no config_get: Expose::Resource',
        );

        $this->assertCount(1, $map->resources);
        $config = $map->resources[0];
        $this->assertSame('app://self/config', $config->uri);
        $this->assertSame('Config', $config->title);
        $this->assertSame('Application configuration', $config->description, 'phpdoc summary');
        $this->assertSame('text/plain', $config->mimeType, '#[Mcp(mimeType:)]');
    }

    public function testExposeToolSuppressesTheResourceProjection(): void
    {
        $templates = array_map(
            static fn (TemplateDescriptor $t): string => $t->uriTemplate,
            $this->projectionAppMap()->templates,
        );

        $this->assertSame(
            ['app://self/detail{?id}', 'app://self/doc{?format}', 'app://self/session{?id}', 'app://self/wiki{?slug}'],
            $templates,
            'no status template (method-level Expose::Tool), no feed template (class-level Expose::Tool)',
        );
    }

    public function testClassLevelExposeAppliesAndMethodLevelOverridesIt(): void
    {
        $map = $this->projectionAppMap();

        $templates = array_map(static fn (TemplateDescriptor $t): string => $t->uriTemplate, $map->templates);
        $this->assertNotContains('app://self/feed{?page}', $templates, 'class-level as: Expose::Tool is inherited');
        $this->assertContains(
            'app://self/wiki{?slug}',
            $templates,
            'method-level as: Expose::Both overrides the class-level Expose::Tool',
        );
    }

    public function testEnumBecomesCompletionCandidates(): void
    {
        $doc = $this->template($this->projectionAppMap(), 'app://self/doc{?format}');

        $this->assertSame(['format' => ['html', 'pdf', 'text']], $doc->completions);
    }

    public function testWebContextParameterIsExcludedFromTemplateVariables(): void
    {
        $session = $this->template($this->projectionAppMap(), 'app://self/session{?id}');

        $this->assertSame(['id'], $session->variables, 'the #[CookieParam] parameter is not a caller argument');
    }

    public function testExposeResourceOnNonGetFailsFast(): void
    {
        $this->expectException(InvalidExposureException::class);
        $this->expectExceptionMessage('Expose::Resource is GET-only');

        $this->factory('FakeVendor\InvalidProject', dirname(__DIR__) . '/Fake/invalid-app')();
    }

    public function testExposeBothOnNonGetFailsFast(): void
    {
        $this->expectException(InvalidExposureException::class);
        $this->expectExceptionMessage('Expose::Both is GET-only');

        $this->factory('FakeVendor\InvalidBothProject', dirname(__DIR__) . '/Fake/invalid-both-app')();
    }

    public function testDuplicateToolNamesFailFast(): void
    {
        $this->expectException(DuplicateToolNameException::class);

        $this->factory('FakeVendor\DupProject', dirname(__DIR__) . '/Fake/dup-app')();
    }
}
