<?php

declare(strict_types=1);

namespace BEAR\Mcp\Module;

use BEAR\Mcp\Map\AnnotationDeriver;
use BEAR\Mcp\Map\AttributeMcpMapFactory;
use BEAR\Mcp\Map\McpMapFactoryInterface;
use BEAR\Mcp\Schema\InputSchemaFactory;
use BEAR\Mcp\Sdk\ServerFactory;
use BEAR\Mcp\Server\McpConfig;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

/**
 * Core MCP bindings
 *
 * json_schema_dir / json_validate_dir are intentionally NOT bound here:
 * Ray.Di uses constructor defaults ('') when they are unbound, and binding
 * them here could shadow the app's JsonSchemaModule (first install wins).
 */
final class McpModule extends AbstractModule
{
    public function __construct(
        private readonly McpConfig $config,
        AbstractModule|null $module = null,
    ) {
        parent::__construct($module);
    }

    protected function configure(): void
    {
        $this->bind(McpConfig::class)->toInstance($this->config);
        $this->bind(McpMapFactoryInterface::class)->to(AttributeMcpMapFactory::class)->in(Scope::SINGLETON);
        $this->bind(AnnotationDeriver::class)->in(Scope::SINGLETON);
        $this->bind(InputSchemaFactory::class)->in(Scope::SINGLETON);
        $this->bind(ServerFactory::class)->in(Scope::SINGLETON);
    }
}
