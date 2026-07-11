<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

use NaokiTsuchiya\BEAR\Mcp\Exception\DuplicateToolNameException;
use PHPUnit\Framework\TestCase;

final class McpMapTest extends TestCase
{
    private function tool(string $name, string $uri, string $verb): ToolDescriptor
    {
        return new ToolDescriptor(
            name: $name,
            uri: $uri,
            verb: $verb,
            title: null,
            description: null,
            inputSchema: ['type' => 'object'],
            safety: (new AnnotationDeriver())($verb),
        );
    }

    public function testMergingToolsFromTwoSourcesWithSameNameFailsFast(): void
    {
        // The construction invariant is what protects an Interop\ToolUseBridge
        // merge: a bridged tool colliding with a #[Mcp] tool fails at boot
        $this->expectException(DuplicateToolNameException::class);
        $this->expectExceptionMessageMatches('/todo_get/');

        new McpMap([
            $this->tool('todo_get', 'app://self/todo', 'get'),
            $this->tool('todo_get', 'app://self/todo-legacy', 'get'),
        ]);
    }

    public function testDistinctNamesConstructFine(): void
    {
        $map = new McpMap(
            [$this->tool('todo_get', 'app://self/todo', 'get')],
            [new ResourceDescriptor('app://self/config', 'config', null, null)],
            [new TemplateDescriptor('app://self/todo{?id}', 'todo', null, null, ['id'])],
        );

        $this->assertCount(1, $map->tools);
        $this->assertCount(1, $map->resources);
        $this->assertCount(1, $map->templates);
    }
}
