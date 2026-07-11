<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Fake;

use BEAR\Resource\AbstractUri;
use BEAR\Resource\FactoryInterface;
use BEAR\Resource\ResourceObject;
use LogicException;
use ReflectionClass;

/**
 * Map URIs straight to resource instances — no DI container in unit tests
 */
final class FakeResourceFactory implements FactoryInterface
{
    /** @param array<string, class-string<ResourceObject>> $map uri => resource class */
    public function __construct(
        private readonly array $map,
    ) {
    }

    /** @param AbstractUri|string $uri */
    public function newInstance($uri): ResourceObject
    {
        $key = (string) $uri;
        if (! isset($this->map[$key])) {
            throw new LogicException('No fake resource for: ' . $key);
        }

        return (new ReflectionClass($this->map[$key]))->newInstanceWithoutConstructor();
    }
}
