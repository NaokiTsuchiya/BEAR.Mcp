<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Schema;

use BEAR\Resource\ResourceObject;
use Ray\WebContextParam\Annotation\AbstractWebContextParam;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionParameter;

use function implode;

/**
 * Expand a GET method signature into an RFC 6570 form-style URI template
 *
 * app://self/todo + onGet(int $id, string $tag) => app://self/todo{?id,tag}
 *
 * Web-context parameters (cookie/env/server/...) are not caller arguments and
 * are excluded — the same rule as InputSchemaFactory's 'in' key exclusion.
 * A method with no remaining variables is a plain resource, not a template.
 */
final class UriTemplateFactory
{
    /** @param class-string<ResourceObject> $class */
    public function __invoke(string $uri, string $class, string $verb): UriTemplate|null
    {
        $method = new ReflectionMethod($class, 'on' . $verb);
        $variables = [];
        foreach ($method->getParameters() as $parameter) {
            if ($this->isWebContext($parameter)) {
                continue;
            }

            $variables[] = $parameter->name;
        }

        if ($variables === []) {
            return null;
        }

        return new UriTemplate($uri . '{?' . implode(',', $variables) . '}', $variables);
    }

    private function isWebContext(ReflectionParameter $parameter): bool
    {
        return $parameter->getAttributes(AbstractWebContextParam::class, ReflectionAttribute::IS_INSTANCEOF) !== [];
    }
}
