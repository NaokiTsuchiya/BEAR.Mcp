<?php

declare(strict_types=1);

namespace BEAR\Mcp\Map;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Mcp\Attribute\Expose;
use BEAR\Mcp\Attribute\Mcp;
use BEAR\Mcp\Attribute\McpExclude;
use BEAR\Mcp\Exception\DuplicateToolNameException;
use BEAR\Mcp\Exception\InvalidExposureException;
use BEAR\Mcp\Schema\InputSchemaFactory;
use ReflectionClass;
use ReflectionMethod;

use function in_array;
use function parse_url;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;
use function usort;

use const PHP_URL_PATH;

/**
 * Build the publication map by scanning #[Mcp] attributes on resource classes
 *
 * Default-closed: only methods carrying (or covered by a class-level)
 * #[Mcp] attribute are exposed. `grep '#[Mcp'` lists the entire MCP surface.
 */
final class AttributeMcpMapFactory implements McpMapFactoryInterface
{
    private const VERBS = ['get', 'post', 'put', 'patch', 'delete'];

    public function __construct(
        private readonly AbstractAppMeta $appMeta,
        private readonly InputSchemaFactory $inputSchemaFactory,
        private readonly AnnotationDeriver $deriver,
    ) {
    }

    public function __invoke(): McpMap
    {
        $tools = [];
        foreach ($this->appMeta->getGenerator('*') as $resMeta) {
            /** @var ReflectionClass<object> $class */
            $class = new ReflectionClass($resMeta->class);
            if ($class->isAbstract()) {
                continue; // not dispatchable; a class-level attribute here would also crash instantiation
            }

            $classAttr = ($class->getAttributes(Mcp::class)[0] ?? null)?->newInstance();
            foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if (! str_starts_with($method->getName(), 'on')) {
                    continue;
                }

                $verb = strtolower(substr($method->getName(), 2));
                if (! in_array($verb, self::VERBS, true)) {
                    continue;
                }

                if ($method->getAttributes(McpExclude::class) !== []) {
                    continue;
                }

                $methodAttr = ($method->getAttributes(Mcp::class)[0] ?? null)?->newInstance();
                if ($methodAttr !== null && $method->getDeclaringClass()->name !== $class->name) {
                    // Exposure never propagates through inheritance: each class must
                    // opt in itself, so `grep '#[Mcp'` on its file shows its surface
                    $methodAttr = null;
                }

                if ($methodAttr === null && $classAttr === null) {
                    continue; // default-closed
                }

                $descriptor = $this->newDescriptor($resMeta->uriPath, $resMeta->class, $verb, $methodAttr, $classAttr);
                if ($descriptor === null) {
                    continue;
                }

                $tools[] = $descriptor;
            }
        }

        usort($tools, static fn (ToolDescriptor $a, ToolDescriptor $b): int => $a->name <=> $b->name);
        $this->assertUniqueNames($tools);

        return new McpMap($tools);
    }

    /** @param class-string<\BEAR\Resource\ResourceObject> $class */
    private function newDescriptor(
        string $uri,
        string $class,
        string $verb,
        Mcp|null $methodAttr,
        Mcp|null $classAttr,
    ): ToolDescriptor|null {
        $expose = $methodAttr?->as ?? $classAttr?->as ?? Expose::Auto;
        if (($expose === Expose::Resource || $expose === Expose::Both) && $verb !== 'get') {
            throw new InvalidExposureException(
                sprintf('Expose::%s is GET-only: %s::on%s', $expose->name, $class, $verb),
            );
        }

        if ($expose === Expose::Resource) {
            // v0.1 publishes tools only; resource projection lands in v0.2
            return null;
        }

        $meta = ($this->inputSchemaFactory)($class, $verb);

        return new ToolDescriptor(
            // name is a per-method (URI + verb) identifier: class-level name is never
            // consulted — inheriting it would collide as soon as two verbs are exposed
            name: $methodAttr?->name ?? $this->deriveName($uri, $verb),
            uri: $uri,
            verb: $verb,
            title: $methodAttr?->title ?? $classAttr?->title,
            description: $methodAttr?->description ?? $classAttr?->description ?? $meta->summary,
            inputSchema: $meta->inputSchema,
            safety: ($this->deriver)(
                $verb,
                $methodAttr?->destructive ?? $classAttr?->destructive,
                $methodAttr?->idempotent ?? $classAttr?->idempotent,
                $methodAttr?->openWorld ?? $classAttr?->openWorld,
            ),
            outputSchema: $meta->outputSchema,
        );
    }

    /**
     * Same derivation rule as BEAR.ToolUse: the same resource gets the same
     * name in an agent loop and in MCP
     */
    private function deriveName(string $uri, string $verb): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);

        return str_replace(['/', '-'], '_', trim($path, '/')) . '_' . $verb;
    }

    /** @param list<ToolDescriptor> $tools */
    private function assertUniqueNames(array $tools): void
    {
        $seen = [];
        foreach ($tools as $tool) {
            if (isset($seen[$tool->name])) {
                throw new DuplicateToolNameException(sprintf(
                    'Tool name "%s" is declared by both %s(on%s) and %s(on%s). Disambiguate with a method-level #[Mcp(name:)].',
                    $tool->name,
                    $seen[$tool->name]->uri,
                    $seen[$tool->name]->verb,
                    $tool->uri,
                    $tool->verb,
                ));
            }

            $seen[$tool->name] = $tool;
        }
    }
}
