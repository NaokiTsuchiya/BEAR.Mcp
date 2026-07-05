<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Schema;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\OptionsMethods;
use BEAR\Resource\ResourceObject;
use Ray\Di\Di\Named;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

use function array_intersect;
use function array_key_exists;
use function array_keys;
use function array_replace;
use function array_unique;
use function array_values;
use function count;
use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function sort;

use const JSON_THROW_ON_ERROR;

/**
 * Build a tool inputSchema from the resource's own self-description
 *
 * "tools/list is OPTIONS * over the published subset": OptionsMethods is
 * injected and called directly, bypassing the renderer, so prod's
 * NullOptionsRenderer (405) has no effect here.
 *
 * Priority: #[JsonSchema(params:)] file (the very schema enforced by the
 * validation AOP) > OptionsMethods-derived reflection/phpdoc metadata.
 */
final class InputSchemaFactory
{
    private const TYPE_MAP = [
        'int' => 'integer',
        'integer' => 'integer',
        'float' => 'number',
        'double' => 'number',
        'number' => 'number',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => 'array',
        'string' => 'string',
    ];

    private readonly OptionsMethods $schemalessOptionsMethods;

    public function __construct(
        private readonly OptionsMethods $optionsMethods,
        #[Named('json_validate_dir')]
        private readonly string $validateDir = '',
    ) {
        // BEAR.Resource <= 1.x: OptionsMethods reads "{json_schema_dir}/" (the directory
        // itself) when #[JsonSchema] has an empty schema:, and json_decode blows up.
        // For those methods, use an instance whose schema lookup can never resolve.
        $this->schemalessOptionsMethods = new OptionsMethods('/dev/null');
    }

    /** @param class-string<ResourceObject> $class */
    public function __invoke(string $class, string $verb): MethodMeta
    {
        $method = new ReflectionMethod($class, 'on' . $verb);
        $attribute = ($method->getAttributes(JsonSchema::class)[0] ?? null)?->newInstance();

        if ($this->hasNonNamedType($method)) {
            // bear/resource OptionsMethods crashes on union/intersection parameter
            // types (asserts ReflectionNamedType) — derive from reflection alone
            $schema = $this->reflectionOnlySchema($method);
            $paramsSchema = $this->paramsFileSchema($attribute);

            return new MethodMeta(
                inputSchema: $paramsSchema === null ? $schema : $this->merge($schema, $paramsSchema),
                outputSchema: null,
                summary: null,
            );
        }

        // OptionsMethods only reflects on the class; no constructor dependencies are needed
        $ro = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $optionsMethods = $attribute !== null && $attribute->schema === ''
            ? $this->schemalessOptionsMethods
            : $this->optionsMethods;
        $options = $optionsMethods($ro, $verb);

        $schema = $this->deriveInputSchema($options, $method);
        $paramsSchema = $this->paramsFileSchema($attribute);
        if ($paramsSchema !== null) {
            $schema = $this->merge($schema, $paramsSchema);
        }

        return new MethodMeta(
            inputSchema: $schema,
            outputSchema: $this->outputSchema($options),
            summary: $options['summary'] ?? $options['description'] ?? null,
        );
    }

    private function hasNonNamedType(ReflectionMethod $method): bool
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type !== null && ! $type instanceof ReflectionNamedType) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function reflectionOnlySchema(ReflectionMethod $method): array
    {
        $properties = [];
        $required = [];
        foreach ($method->getParameters() as $parameter) {
            $properties[$parameter->name] = $this->propertyFromType($parameter->getType());
            if (! $parameter->isDefaultValueAvailable()) {
                $required[] = $parameter->name;
                continue;
            }

            if ($parameter->getDefaultValue() !== null) {
                $properties[$parameter->name]['default'] = $parameter->getDefaultValue();
            }
        }

        $schema = ['type' => 'object'];
        if ($properties !== []) {
            $schema['properties'] = $properties;
        }

        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    private function propertyFromType(\ReflectionType|null $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return ['type' => self::TYPE_MAP[$type->getName()] ?? 'string'];
        }

        if ($type instanceof ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $member) {
                if (! $member instanceof ReflectionNamedType) {
                    continue;
                }

                $types[] = $member->getName() === 'null' ? 'null' : self::TYPE_MAP[$member->getName()] ?? 'string';
            }

            $types = array_values(array_unique($types));
            sort($types); // reflection does not preserve declaration order; keep the wire deterministic
            if ($types !== []) {
                return ['type' => count($types) === 1 ? $types[0] : $types];
            }
        }

        // intersection types and untyped parameters
        return ['type' => $type === null ? 'string' : 'object'];
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function deriveInputSchema(array $options, ReflectionMethod $method): array
    {
        $properties = [];
        foreach ($options['request']['parameters'] ?? [] as $name => $meta) {
            if (isset($meta['in'])) {
                // Web-context parameters (cookie/env/server/...) are not caller arguments
                continue;
            }

            $property = ['type' => self::TYPE_MAP[$meta['type'] ?? ''] ?? 'string'];
            if (isset($meta['description'])) {
                $property['description'] = $meta['description'];
            }

            if (array_key_exists('default', $meta)) {
                $property['default'] = $meta['default'];
            }

            $properties[$name] = $property;
        }

        // OptionsMethods stringifies default values (false becomes ''); recover the
        // typed defaults from reflection so they match the declared JSON type
        foreach ($method->getParameters() as $parameter) {
            if (! isset($properties[$parameter->name]) || ! $parameter->isDefaultValueAvailable()) {
                continue;
            }

            $default = $parameter->getDefaultValue();
            if ($default === null) {
                // a null default contradicts the non-nullable JSON type; optionality
                // is already expressed by the parameter's absence from `required`
                unset($properties[$parameter->name]['default']);
                continue;
            }

            $properties[$parameter->name]['default'] = $default;
        }

        $schema = ['type' => 'object'];
        if ($properties !== []) {
            $schema['properties'] = $properties;
        }

        $required = array_values(array_intersect(
            $options['request']['required'] ?? [],
            array_keys($properties),
        ));
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /** @return array<string, mixed>|null */
    private function paramsFileSchema(JsonSchema|null $attribute): array|null
    {
        if ($this->validateDir === '' || $attribute === null || $attribute->params === '') {
            return null;
        }

        $file = $this->validateDir . '/' . $attribute->params;
        if (! file_exists($file)) {
            return null;
        }

        $schema = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return is_array($schema) ? $schema : null;
    }

    /**
     * The params file wins key by key; reflection-derived data fills the gaps
     *
     * @param array<string, mixed> $derived
     * @param array<string, mixed> $file
     *
     * @return array<string, mixed>
     */
    private function merge(array $derived, array $file): array
    {
        $merged = array_replace($derived, $file);
        $merged['type'] = 'object';

        $properties = $file['properties'] ?? [];
        foreach ($derived['properties'] ?? [] as $name => $property) {
            $properties[$name] = ($properties[$name] ?? []) + $property;
        }

        if ($properties !== []) {
            $merged['properties'] = $properties;
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>|null
     */
    private function outputSchema(array $options): array|null
    {
        $schema = $options['schema'] ?? null;
        if (! is_array($schema) || ($schema['type'] ?? null) !== 'object') {
            // The MCP spec constrains outputSchema to a root object
            return null;
        }

        return $schema;
    }
}
