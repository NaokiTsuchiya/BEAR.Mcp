<?php

declare(strict_types=1);

namespace BEAR\Mcp\Sdk;

use BEAR\Mcp\Map\McpMapFactoryInterface;
use BEAR\Mcp\Map\ToolDescriptor;
use BEAR\Mcp\Sdk\Handler\InitializeHandler;
use BEAR\Mcp\Sdk\Handler\ResourceToolHandler;
use BEAR\Mcp\Server\McpConfig;
use BEAR\Resource\ResourceInterface;
use Mcp\Schema\Implementation;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\Tool;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;

/**
 * The single assembly point: build an MCP server from the publication map
 *
 * mcp/sdk types stay inside the Sdk\ namespace — this is the BC-break
 * absorption wall against the pre-1.0 SDK.
 */
final class ServerFactory
{
    public function __construct(
        private readonly McpConfig $config,
        private readonly McpMapFactoryInterface $mapFactory,
        private readonly ResourceInterface $resource,
    ) {
    }

    public function create(): Server
    {
        // Declare only what v0.1 supports: tools, no listChanged (the map is
        // static after boot). The SDK default would announce logging and
        // completions this server cannot honor.
        $capabilities = new ServerCapabilities(tools: true, resources: false, prompts: false);

        $builder = Server::builder()
            ->setServerInfo($this->config->name, $this->config->version)
            ->setCapabilities($capabilities)
            ->addRequestHandler(new InitializeHandler(
                $capabilities,
                new Implementation($this->config->name, $this->config->version),
                $this->config->instructions,
            ));
        if ($this->config->instructions !== null) {
            $builder->setInstructions($this->config->instructions);
        }

        foreach (($this->mapFactory)()->tools as $descriptor) {
            $builder->add(
                $this->sdkTool($descriptor),
                new ResourceToolHandler($this->resource, $descriptor),
            );
        }

        return $builder->build();
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
