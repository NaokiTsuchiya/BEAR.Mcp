<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

interface McpMapFactoryInterface
{
    public function __invoke(): McpMap;
}
