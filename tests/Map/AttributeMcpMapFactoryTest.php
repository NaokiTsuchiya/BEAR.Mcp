<?php

declare(strict_types=1);

namespace BEAR\Mcp\Map;

use BEAR\AppMeta\Meta;
use BEAR\Mcp\Exception\DuplicateToolNameException;
use BEAR\Mcp\Exception\InvalidExposureException;
use BEAR\Mcp\Schema\InputSchemaFactory;
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
        );
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
            ['multi_get', 'multi_post', 'search_get', 'todo_archive', 'todo_get', 'todo_post', 'user_get'],
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

    public function testExposeResourceOnNonGetFailsFast(): void
    {
        $this->expectException(InvalidExposureException::class);

        $this->factory('FakeVendor\InvalidProject', dirname(__DIR__) . '/Fake/invalid-app')();
    }

    public function testDuplicateToolNamesFailFast(): void
    {
        $this->expectException(DuplicateToolNameException::class);

        $this->factory('FakeVendor\DupProject', dirname(__DIR__) . '/Fake/dup-app')();
    }
}
