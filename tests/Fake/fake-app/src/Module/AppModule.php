<?php

declare(strict_types=1);

namespace FakeVendor\FakeProject\Module;

use NaokiTsuchiya\BEAR\Mcp\Module\McpModule;
use NaokiTsuchiya\BEAR\Mcp\Server\McpConfig;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\Resource\Module\JsonSchemaModule;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        $this->install(new McpModule(new McpConfig(
            name: 'fake-app',
            version: '1.0.0',
            instructions: 'Fake BEAR.Sunday app for BEAR.Mcp tests.',
        )));
        $this->install(new JsonSchemaModule(
            $this->appMeta->appDir . '/var/json_schema',
            $this->appMeta->appDir . '/var/json_validate',
        ));
        $this->install(new PackageModule());
    }
}
