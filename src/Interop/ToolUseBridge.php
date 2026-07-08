<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Interop;

use BEAR\Resource\FactoryInterface;
use BEAR\ToolUse\Attribute\Exclude;
use BEAR\ToolUse\Schema\SchemaConverterInterface;
use BEAR\ToolUse\Schema\Tool;
use NaokiTsuchiya\BEAR\Mcp\Exception\ToolUseContractException;
use NaokiTsuchiya\BEAR\Mcp\Map\AnnotationDeriver;
use NaokiTsuchiya\BEAR\Mcp\Map\ToolDescriptor;
use ReflectionClass;

use function count;
use function interface_exists;
use function is_array;
use function is_string;
use function ltrim;
use function parse_url;
use function sprintf;
use function ucfirst;

use const PHP_URL_PATH;

/**
 * Publish bear/tool-use tool definitions as MCP tools
 *
 * Soft dependency: bear/tool-use is suggested, never required — check
 * isAvailable() before wiring. Default-closed is preserved: only the URIs the
 * application passes explicitly are collected; ToolUse's opt-out collection
 * model is not imported.
 *
 * The bridge reads Schema\Tool object properties, never jsonSerialize()
 * (which emits snake_case input_schema plus a non-standard confirm key), and
 * pairs each tool with its real HTTP verb at collection time — ToolUse's
 * ToolRegistry infers the verb from the tool name and falls back to 'get'
 * for custom #[Tool(name:)] values, which would misdispatch here.
 *
 * Dispatch stays on this package's ResourceToolHandler: merge the returned
 * descriptors with the #[Mcp] map via `new McpMap([...])`, where a name
 * collision fails fast with DuplicateToolNameException.
 */
final class ToolUseBridge
{
    /** SchemaConverter's iteration order (onGet, onPost, ...) */
    private const VERBS = ['get', 'post', 'put', 'patch', 'delete'];

    public function __construct(
        private readonly SchemaConverterInterface $converter,
        private readonly FactoryInterface $factory,
        private readonly AnnotationDeriver $deriver,
    ) {
    }

    /** Whether bear/tool-use is installed (::class constants do not autoload) */
    public static function isAvailable(): bool
    {
        return interface_exists(SchemaConverterInterface::class);
    }

    /**
     * @param list<string> $uris Explicit allowlist of resource URIs to publish
     *
     * @return list<ToolDescriptor>
     */
    public function __invoke(array $uris): array
    {
        $descriptors = [];
        foreach ($uris as $uri) {
            foreach ($this->collect($uri) as $descriptor) {
                $descriptors[] = $descriptor;
            }
        }

        return $descriptors;
    }

    /** @return list<ToolDescriptor> */
    private function collect(string $uri): array
    {
        $class = $this->factory->newInstance($uri)::class;
        $tools = $this->converter->convert($class, $this->extractPath($uri));
        $verbs = $this->exposedVerbs($class);
        if (count($tools) !== count($verbs)) {
            throw new ToolUseContractException(sprintf(
                'Cannot pair %d tool(s) from SchemaConverter with %d verb method(s) on %s — bear/tool-use changed its collection rules',
                count($tools),
                count($verbs),
                $class,
            ));
        }

        $descriptors = [];
        foreach ($tools as $i => $tool) {
            $descriptors[] = $this->descriptor($tool, $uri, $verbs[$i]);
        }

        return $descriptors;
    }

    /**
     * Replicate SchemaConverter's method iteration to recover the real verbs:
     * fixed verb order, class- and method-level #[Exclude] honored
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    private function exposedVerbs(string $class): array
    {
        $reflection = new ReflectionClass($class);
        if ($reflection->getAttributes(Exclude::class) !== []) {
            return [];
        }

        $verbs = [];
        foreach (self::VERBS as $verb) {
            if (! $reflection->hasMethod('on' . ucfirst($verb))) {
                continue;
            }

            if ($reflection->getMethod('on' . ucfirst($verb))->getAttributes(Exclude::class) !== []) {
                continue;
            }

            $verbs[] = $verb;
        }

        return $verbs;
    }

    private function descriptor(Tool $tool, string $uri, string $verb): ToolDescriptor
    {
        $inputSchema = $this->normalize($tool->inputSchema);
        // SchemaConverter always emits properties/required; empty they would
        // serialize as JSON arrays ([] is not a valid `properties` object)
        if (($inputSchema['properties'] ?? null) === []) {
            unset($inputSchema['properties']);
        }

        if (($inputSchema['required'] ?? null) === []) {
            unset($inputSchema['required']);
        }

        return new ToolDescriptor(
            name: $tool->name,
            uri: $uri,
            verb: $verb,
            title: null,
            description: $tool->description === '' ? null : $tool->description,
            inputSchema: $inputSchema,
            // ToolUse's confirm ("ask the human first") maps onto destructiveHint —
            // the annotation MCP hosts use to gate confirmation dialogs
            safety: ($this->deriver)($verb, destructive: $tool->confirm ? true : null),
            outputSchema: null,
        );
    }

    /**
     * SchemaConverter emits OpenAPI-style `nullable: true`; JSON Schema
     * 2020-12 expresses that as `type: [T, "null"]`
     *
     * @param array<array-key, mixed> $schema
     *
     * @return array<array-key, mixed>
     */
    private function normalize(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = $this->normalize($value);
            }
        }

        if (($schema['nullable'] ?? null) === true) {
            unset($schema['nullable']);
            if (isset($schema['type']) && is_string($schema['type'])) {
                $schema['type'] = [$schema['type'], 'null'];
            }
        }

        return $schema;
    }

    private function extractPath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        return '/' . ltrim(is_string($path) ? $path : '', '/');
    }
}
