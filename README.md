# BEAR.Mcp

Serve a [BEAR.Sunday](https://bearsunday.github.io/) application as an [MCP](https://modelcontextprotocol.io/) server.

BEAR.Mcp is not a router and not an adapter — it is a third protocol binding alongside HTTP and CLI. Resource methods you mark with `#[Mcp]` are published as MCP tools, with their names, descriptions, input schemas, and risk annotations derived from what the resource already declares: HTTP verb semantics, phpdoc, `#[JsonSchema]` files, and OPTIONS metadata.

**Status: v0.2 (experimental).** Tools over stdio and Streamable HTTP. Resources / resource templates and `resource_link` are planned — see [DESIGN.md](DESIGN.md).

## Usage

Mark the resource methods you want to expose. Nothing is published without an attribute (default-closed):

```php
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use BEAR\Resource\Annotation\JsonSchema;

class Todo extends ResourceObject
{
    /**
     * Get a todo by ID
     *
     * @param int $id Todo ID
     */
    #[Mcp]                                                      // tool "todo_get" (readOnlyHint: true)
    #[JsonSchema(schema: 'todo.json', params: 'todo.get.json')] // params file becomes the inputSchema
    public function onGet(int $id): static { /* ... */ }

    /** Create a new todo */
    #[Mcp]                                                      // tool "todo_post"
    public function onPost(string $title): static { /* ... */ }

    #[Mcp(name: 'todo_archive', destructive: false)]            // soft delete: override the verb-derived hint
    public function onDelete(int $id): static { /* ... */ }

    public function onPut(int $id, string $title): static { /* not exposed */ }
}
```

Install the module:

```php
use NaokiTsuchiya\BEAR\Mcp\Module\McpModule;
use NaokiTsuchiya\BEAR\Mcp\Server\McpConfig;

$this->install(new McpModule(new McpConfig(
    name: 'my-app',
    version: '1.0.0',
    instructions: 'Manage todos.',
)));
```

Register with an MCP client (e.g. `claude_desktop_config.json`) — no files to add to your app:

```json
{
    "mcpServers": {
        "my-app": {
            "command": "php",
            "args": ["/path/to/app/vendor/bin/bear-mcp", "MyVendor\\MyApp", "prod-app", "/path/to/app"]
        }
    }
}
```

## Streamable HTTP

Install the HTTP module alongside `McpModule`:

```php
$this->install(new McpHttpModule(
    allowedHosts: ['mcp.example.com'],   // omit for localhost-only (secure default)
));
```

On plain PHP-FPM, drop a public endpoint script:

```php
// public/mcp.php
use NaokiTsuchiya\BEAR\Mcp\Sdk\Transport\McpHttpEndpoint;

require dirname(__DIR__) . '/vendor/autoload.php';
(new McpHttpEndpoint())('MyVendor\MyApp', 'prod-app', dirname(__DIR__));
```

On a PSR-15 stack (FrankenPHP worker, RoadRunner, middleware pipelines), mount
`McpRequestHandler` directly — it is a standard `RequestHandlerInterface`.

Sessions are file-backed under `var/tmp/{context}/mcp-sessions` (created
owner-only: file names are live session ids) and requests on the same session
are serialized with a per-session lock — the SDK's session handling is a
whole-blob read-modify-write, so parallel requests on one session would race
otherwise. The lock serializes per host: multi-host deployments need sticky
sessions, and can rebind the SDK's `SessionStoreInterface` (e.g.
`Psr16SessionStore` on Redis) for shared session state. CORS, DNS-rebinding
protection, and protocol-version validation come from the SDK's default
middleware.

**Authentication is not included**: this binding targets a trusted boundary.
Put a Bearer/OAuth middleware or gateway in front of the endpoint before
exposing it beyond localhost.

## What is derived for you

| Source | MCP |
|---|---|
| HTTP verb (`onGet`/`onPost`/...) | `readOnlyHint` / `destructiveHint` / `idempotentHint` (RFC 9110 semantics) |
| phpdoc summary / `@param` | tool description / parameter descriptions |
| parameter types and defaults | `inputSchema` types, defaults, `required` |
| `#[JsonSchema(params:)]` file | `inputSchema` (highest priority — the same schema the validation AOP enforces) |
| `#[JsonSchema(schema:)]` file | `outputSchema` + `structuredContent` |

All tools default to `openWorldHint: false` (your app is a closed domain). Business errors (4xx/5xx resource codes, exceptions) become `isError` tool results the model can self-correct from, never protocol errors.

## Requirements

- PHP 8.2+
- BEAR.Sunday (bear/package ^1.13)
