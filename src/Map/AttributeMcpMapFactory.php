<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

use BEAR\AppMeta\AbstractAppMeta;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Expose;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use NaokiTsuchiya\BEAR\Mcp\Attribute\McpExclude;
use NaokiTsuchiya\BEAR\Mcp\Exception\InvalidExposureException;
use NaokiTsuchiya\BEAR\Mcp\Schema\InputSchemaFactory;
use NaokiTsuchiya\BEAR\Mcp\Schema\MethodMeta;
use NaokiTsuchiya\BEAR\Mcp\Schema\UriTemplateFactory;
use ReflectionClass;
use ReflectionMethod;

use function in_array;
use function is_array;
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
 *
 * GET methods are projected twice by default (Expose::Auto): as a readOnly
 * tool and as an MCP resource (argument-less) or resource template (with
 * arguments) — two projections of the same onGet, one implementation.
 */
final class AttributeMcpMapFactory implements McpMapFactoryInterface
{
    private const VERBS = ['get', 'post', 'put', 'patch', 'delete'];

    private const DEFAULT_MIME_TYPE = 'application/json';

    public function __construct(
        private readonly AbstractAppMeta $appMeta,
        private readonly InputSchemaFactory $inputSchemaFactory,
        private readonly AnnotationDeriver $deriver,
        private readonly UriTemplateFactory $uriTemplateFactory,
    ) {
    }

    public function __invoke(): McpMap
    {
        $tools = [];
        $resources = [];
        $templates = [];
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

                $expose = $methodAttr?->as ?? $classAttr?->as ?? Expose::Auto;
                if (($expose === Expose::Resource || $expose === Expose::Both) && $verb !== 'get') {
                    throw new InvalidExposureException(
                        sprintf('Expose::%s is GET-only: %s::on%s', $expose->name, $resMeta->class, $verb),
                    );
                }

                $meta = ($this->inputSchemaFactory)($resMeta->class, $verb);

                if ($expose !== Expose::Resource) {
                    $tools[] = $this->newToolDescriptor($resMeta->uriPath, $verb, $methodAttr, $classAttr, $meta);
                }

                if ($verb === 'get' && $expose !== Expose::Tool) {
                    $template = ($this->uriTemplateFactory)($resMeta->uriPath, $resMeta->class, $verb);
                    if ($template === null) {
                        $resources[] = $this->newResourceDescriptor($resMeta->uriPath, $methodAttr, $classAttr, $meta);
                        continue;
                    }

                    $templates[] = $this->newTemplateDescriptor(
                        $resMeta->uriPath,
                        $template->template,
                        $template->variables,
                        $methodAttr,
                        $classAttr,
                        $meta,
                    );
                }
            }
        }

        usort($tools, static fn (ToolDescriptor $a, ToolDescriptor $b): int => $a->name <=> $b->name);
        usort($resources, static fn (ResourceDescriptor $a, ResourceDescriptor $b): int => $a->uri <=> $b->uri);
        usort(
            $templates,
            static fn (TemplateDescriptor $a, TemplateDescriptor $b): int => $a->uriTemplate <=> $b->uriTemplate,
        );

        return new McpMap($tools, $resources, $templates);
    }

    private function newToolDescriptor(
        string $uri,
        string $verb,
        Mcp|null $methodAttr,
        Mcp|null $classAttr,
        MethodMeta $meta,
    ): ToolDescriptor {
        return new ToolDescriptor(
            // name is a per-method (URI + verb) identifier: class-level name is never
            // consulted — inheriting it would collide as soon as two verbs are exposed
            name: $methodAttr?->name ?? $this->derivePath($uri) . '_' . $verb,
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

    private function newResourceDescriptor(
        string $uri,
        Mcp|null $methodAttr,
        Mcp|null $classAttr,
        MethodMeta $meta,
    ): ResourceDescriptor {
        return new ResourceDescriptor(
            uri: $uri,
            // the URI is the identity; the name is a display identifier, so the
            // verb-less path form reads naturally ("todo", not "todo_get")
            name: $this->derivePath($uri),
            title: $methodAttr?->title ?? $classAttr?->title,
            description: $methodAttr?->description ?? $classAttr?->description ?? $meta->summary,
            mimeType: $methodAttr?->mimeType ?? $classAttr?->mimeType ?? self::DEFAULT_MIME_TYPE,
        );
    }

    /** @param list<string> $variables */
    private function newTemplateDescriptor(
        string $uri,
        string $uriTemplate,
        array $variables,
        Mcp|null $methodAttr,
        Mcp|null $classAttr,
        MethodMeta $meta,
    ): TemplateDescriptor {
        return new TemplateDescriptor(
            uriTemplate: $uriTemplate,
            name: $this->derivePath($uri),
            title: $methodAttr?->title ?? $classAttr?->title,
            description: $methodAttr?->description ?? $classAttr?->description ?? $meta->summary,
            variables: $variables,
            completions: $this->completions($variables, $meta),
            mimeType: $methodAttr?->mimeType ?? $classAttr?->mimeType ?? self::DEFAULT_MIME_TYPE,
        );
    }

    /**
     * Enum constraints double as completion candidates — the very values the
     * validation AOP accepts are the values offered to completion/complete
     *
     * @param list<string> $variables
     *
     * @return array<string, list<bool|float|int|string>>
     */
    private function completions(array $variables, MethodMeta $meta): array
    {
        $completions = [];
        foreach ($variables as $variable) {
            $enum = $meta->inputSchema['properties'][$variable]['enum'] ?? null;
            if (! is_array($enum) || $enum === []) {
                continue;
            }

            /** @var list<bool|float|int|string> $enum */
            $completions[$variable] = $enum;
        }

        return $completions;
    }

    /**
     * Same derivation rule as BEAR.ToolUse: the same resource gets the same
     * name in an agent loop and in MCP
     */
    private function derivePath(string $uri): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);

        return str_replace(['/', '-'], '_', trim($path, '/'));
    }
}
