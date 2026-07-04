<?php

declare(strict_types=1);

namespace BEAR\Mcp\Map;

interface McpMapFactoryInterface
{
    public function __invoke(): McpMap;
}
