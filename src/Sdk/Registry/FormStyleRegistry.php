<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Registry;

use Mcp\Capability\Registry;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ResourceReference;
use Mcp\Capability\Registry\ResourceTemplateReference;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Capability\RegistryInterface;
use Mcp\Exception\InvalidCursorException;
use Mcp\Exception\ResourceNotFoundException;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;

use function array_slice;
use function array_values;
use function base64_decode;
use function base64_encode;
use function count;
use function is_numeric;

final class FormStyleRegistry implements RegistryInterface
{
    private readonly Registry $inner;

    /** @var array<string, FormStyleTemplateReference> */
    private array $templates = [];

    public function __construct()
    {
        $this->inner = new Registry();
    }

    public function registerTool(Tool $tool, callable|array|string $handler): ToolReference
    {
        return $this->inner->registerTool($tool, $handler);
    }

    public function registerResource(ResourceDefinition $resource, callable|array|string $handler): ResourceReference
    {
        return $this->inner->registerResource($resource, $handler);
    }

    /** @param array<string, class-string|object> $completionProviders */
    public function registerResourceTemplate(
        ResourceTemplate $template,
        callable|array|string $handler,
        array $completionProviders = [],
    ): ResourceTemplateReference {
        $reference = new FormStyleTemplateReference($template, $handler, $completionProviders);
        $this->templates[$template->uriTemplate] = $reference;

        return $reference;
    }

    /** @param array<string, class-string|object> $completionProviders */
    public function registerPrompt(
        Prompt $prompt,
        callable|array|string $handler,
        array $completionProviders = [],
    ): PromptReference {
        return $this->inner->registerPrompt($prompt, $handler, $completionProviders);
    }

    public function unregisterTool(string $name): void
    {
        $this->inner->unregisterTool($name);
    }

    public function unregisterResource(string $uri): void
    {
        $this->inner->unregisterResource($uri);
    }

    public function unregisterResourceTemplate(string $uriTemplate): void
    {
        unset($this->templates[$uriTemplate]);
    }

    public function unregisterPrompt(string $name): void
    {
        $this->inner->unregisterPrompt($name);
    }

    public function hasTool(string $name): bool
    {
        return $this->inner->hasTool($name);
    }

    public function hasResource(string $uri): bool
    {
        return $this->inner->hasResource($uri);
    }

    public function hasResourceTemplate(string $uriTemplate): bool
    {
        return isset($this->templates[$uriTemplate]);
    }

    public function hasPrompt(string $name): bool
    {
        return $this->inner->hasPrompt($name);
    }

    public function hasTools(): bool
    {
        return $this->inner->hasTools();
    }

    public function getTools(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->inner->getTools($limit, $cursor);
    }

    public function getTool(string $name): ToolReference
    {
        return $this->inner->getTool($name);
    }

    public function hasResources(): bool
    {
        return $this->inner->hasResources();
    }

    public function getResources(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->inner->getResources($limit, $cursor);
    }

    public function getResource(string $uri, bool $includeTemplates = true): ResourceReference|ResourceTemplateReference
    {
        if ($this->inner->hasResource($uri)) {
            return $this->inner->getResource($uri, false);
        }

        if (! $includeTemplates) {
            throw new ResourceNotFoundException($uri);
        }

        if (isset($this->templates[$uri])) {
            return $this->templates[$uri];
        }

        foreach ($this->templates as $template) {
            if ($template->matches($uri)) {
                return $template;
            }
        }

        throw new ResourceNotFoundException($uri);
    }

    public function hasResourceTemplates(): bool
    {
        return [] !== $this->templates;
    }

    public function getResourceTemplates(?int $limit = null, ?string $cursor = null): Page
    {
        $templates = [];
        foreach ($this->templates as $reference) {
            $templates[$reference->resourceTemplate->uriTemplate] = $reference->resourceTemplate;
        }

        if (null === $limit) {
            return new Page($templates, null);
        }

        $paginated = $this->paginate($templates, $limit, $cursor);
        $nextCursor = $this->nextCursor(count($templates), $cursor, $limit);

        return new Page($paginated, $nextCursor);
    }

    public function getResourceTemplate(string $uriTemplate): ResourceTemplateReference
    {
        return $this->templates[$uriTemplate] ?? throw new ResourceNotFoundException($uriTemplate);
    }

    public function hasPrompts(): bool
    {
        return $this->inner->hasPrompts();
    }

    public function getPrompts(?int $limit = null, ?string $cursor = null): Page
    {
        return $this->inner->getPrompts($limit, $cursor);
    }

    public function getPrompt(string $name): PromptReference
    {
        return $this->inner->getPrompt($name);
    }

    /**
     * @param array<int|string, mixed> $items
     *
     * @return array<int|string, mixed>
     */
    private function paginate(array $items, int $limit, ?string $cursor): array
    {
        $offset = 0;
        if (null !== $cursor) {
            $decoded = base64_decode($cursor, true);
            if (false === $decoded || ! is_numeric($decoded)) {
                throw new InvalidCursorException($cursor);
            }

            $offset = (int) $decoded;
            if ($offset < 0 || $offset > count($items)) {
                throw new InvalidCursorException($cursor);
            }
        }

        return array_values(array_slice($items, $offset, $limit));
    }

    private function nextCursor(int $totalItems, ?string $currentCursor, int $limit): ?string
    {
        $currentOffset = 0;
        if (null !== $currentCursor) {
            $decoded = base64_decode($currentCursor, true);
            if (false !== $decoded && is_numeric($decoded)) {
                $currentOffset = (int) $decoded;
            }
        }

        $nextOffset = $currentOffset + $limit;

        if ($nextOffset < $totalItems) {
            return base64_encode((string) $nextOffset);
        }

        return null;
    }
}
