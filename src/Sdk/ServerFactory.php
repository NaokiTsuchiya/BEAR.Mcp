<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk;

use NaokiTsuchiya\BEAR\Mcp\Map\LinkResolver;
use NaokiTsuchiya\BEAR\Mcp\Map\McpMapFactoryInterface;
use NaokiTsuchiya\BEAR\Mcp\Map\ResourceDescriptor;
use NaokiTsuchiya\BEAR\Mcp\Map\TemplateDescriptor;
use NaokiTsuchiya\BEAR\Mcp\Map\ToolDescriptor;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Handler\InitializeHandler;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Handler\ResourceReadHandler;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Handler\ResourceToolHandler;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Handler\TemplateReadHandler;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Registry\FormStyleRegistry;
use NaokiTsuchiya\BEAR\Mcp\Server\McpConfig;
use BEAR\Resource\ResourceInterface;
use Mcp\Capability\Completion\ListCompletionProvider;
use Mcp\Schema\Implementation;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Session\SessionStoreInterface;

use function is_bool;

/**
 * The single assembly point: build an MCP server from the publication map
 *
 * mcp/sdk types stay inside the Sdk\ namespace — this is the BC-break
 * absorption wall against the pre-1.0 SDK.
 */
final class ServerFactory
{
    /**
     * $sessionStore stays null for stdio (the SDK's in-memory default fits a
     * single-client process); McpHttpModule binds a persistent store so
     * sessions survive across HTTP requests
     */
    public function __construct(
        private readonly McpConfig $config,
        private readonly McpMapFactoryInterface $mapFactory,
        private readonly ResourceInterface $resource,
        private readonly LinkResolver $linkResolver,
        private readonly SessionStoreInterface|null $sessionStore = null,
    ) {
    }

    public function create(): Server
    {
        $map = ($this->mapFactory)();

        // Declare only what the map actually publishes, no listChanged (the
        // map is static after boot). The SDK default would announce logging
        // this server cannot honor.
        $capabilities = new ServerCapabilities(
            tools: $map->tools !== [],
            resources: $map->resources !== [] || $map->templates !== [],
            prompts: false,
            completions: $map->templates !== [],
        );

        $builder = Server::builder()
            ->setServerInfo($this->config->name, $this->config->version)
            ->setCapabilities($capabilities)
            ->setRegistry(new FormStyleRegistry())
            ->addRequestHandler(new InitializeHandler(
                $capabilities,
                new Implementation($this->config->name, $this->config->version),
                $this->config->instructions,
            ));
        if ($this->config->instructions !== null) {
            $builder->setInstructions($this->config->instructions);
        }

        if ($this->sessionStore !== null) {
            $builder->setSession($this->sessionStore);
        }

        foreach ($map->tools as $descriptor) {
            $builder->add(
                $this->sdkTool($descriptor),
                new ResourceToolHandler($this->resource, $descriptor, $this->linkResolver),
            );
        }

        foreach ($map->resources as $descriptor) {
            $builder->add(
                new ResourceDefinition($descriptor->uri, $descriptor->name, $descriptor->title, $descriptor->description, $descriptor->mimeType),
                new ResourceReadHandler($this->resource, $descriptor),
            );
        }

        foreach ($map->templates as $descriptor) {
            $builder->add(
                new ResourceTemplate($descriptor->uriTemplate, $descriptor->name, $descriptor->title, $descriptor->description, $descriptor->mimeType),
                new TemplateReadHandler($this->resource, $descriptor),
                $this->completionProviders($descriptor),
            );
        }

        return $builder->build();
    }

    /** @return array<string, ListCompletionProvider> */
    private function completionProviders(TemplateDescriptor $descriptor): array
    {
        $providers = [];
        foreach ($descriptor->completions as $variable => $values) {
            $stringified = [];
            foreach ($values as $value) {
                // uri_template() renders false as '' — LinkResolver encodes
                // booleans explicitly as '1'/'0'; mirror that here so a value
                // round-trips identically through a resource_link or a
                // completion candidate.
                $stringified[] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }

            $providers[$variable] = new ListCompletionProvider($stringified);
        }

        return $providers;
    }

    private function sdkTool(ToolDescriptor $descriptor): Tool
    {
        return new Tool(
            name: $descriptor->name,
            title: $descriptor->title,
            inputSchema: $descriptor->inputSchema,
            description: $descriptor->description,
            annotations: new ToolAnnotations(
                readOnlyHint: $descriptor->safety->readOnly,
                destructiveHint: $descriptor->safety->destructive,
                idempotentHint: $descriptor->safety->idempotent,
                openWorldHint: $descriptor->safety->openWorld,
            ),
            outputSchema: $descriptor->outputSchema,
        );
    }
}
