<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Resource\App;

use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class Todo extends ResourceObject
{
    /** @var array<int, array{id: int, title: string, done: bool}> */
    private const TODOS = [
        1 => ['id' => 1, 'title' => 'Write tests', 'done' => false],
        2 => ['id' => 2, 'title' => 'Ship v0.1', 'done' => true],
    ];

    /**
     * Get a todo by ID
     *
     * @param int $id Todo ID
     */
    #[Mcp]
    #[JsonSchema(schema: 'todo.json', params: 'todo.get.json')]
    #[Link(rel: 'archive', href: '/todo{?id}', method: 'delete', title: 'Archive this todo')]
    #[Link(rel: 'archive', href: 'app://self/todo/archive?id={id}', method: 'get', title: 'View archived todo')]
    public function onGet(int $id): static
    {
        if (! isset(self::TODOS[$id])) {
            $this->code = 404;
            $this->body = ['message' => 'Todo not found: ' . $id];

            return $this;
        }

        $this->body = self::TODOS[$id];

        return $this;
    }

    /**
     * Create a new todo
     *
     * @param string $title Todo title
     * @param bool   $done  Completion state
     */
    #[Mcp]
    public function onPost(string $title, bool $done = false): static
    {
        echo 'stdout-leak-test'; // exercises the per-call stdout guard
        trigger_error('notice-leak-test', E_USER_NOTICE); // display_errors output must not reach stdout either

        $this->code = 201;
        $this->body = ['id' => 3, 'title' => $title, 'done' => $done];

        return $this;
    }

    /**
     * Archive a todo (soft delete)
     *
     * @param int $id Todo ID
     */
    #[Mcp(name: 'todo_archive', destructive: false)]
    public function onDelete(int $id): static
    {
        $this->body = ['archived' => $id];

        return $this;
    }

    /** Not exposed: no #[Mcp] attribute (default-closed) */
    public function onPut(int $id, string $title): static
    {
        $this->body = ['id' => $id, 'title' => $title];

        return $this;
    }

    /** Public helper that must never be mistaken for a verb method */
    public function doGet(int $id): int
    {
        return $id;
    }
}
