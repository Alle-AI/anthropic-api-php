<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools\Schema;

use AlleAI\Anthropic\Tools\Schema\Attributes\Enum as EnumAttribute;
use AlleAI\Anthropic\Tools\Schema\Attributes\Param;

/**
 * Builds a JSON Schema for a method's parameters via reflection.
 *
 * Mapping rules:
 *   - `string` → `{"type": "string"}`
 *   - `int`    → `{"type": "integer"}`
 *   - `float`  → `{"type": "number"}`
 *   - `bool`   → `{"type": "boolean"}`
 *   - `array`  → `{"type": "array"}` (unless docblock indicates object shape)
 *   - backed enum → `{"type": "string|integer", "enum": [...]}`
 *   - nullable T → schema for T plus `{"nullable": true}`
 *   - default value → parameter omitted from `required`
 *   - `#[Param(description: '...')]` → adds `description` field
 *   - `#[Enum('a', 'b')]` → adds `enum` field
 */
final class SchemaGenerator
{
    /**
     * @param  object|class-string  $target
     *
     * @return array<string, mixed>
     */
    public static function fromMethod(object|string $target, string $method): array
    {
        $reflection = new \ReflectionMethod($target, $method);

        $properties = [];
        $required = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $properties[$name] = self::reflectParameter($param);

            if (!$param->isOptional()) {
                $required[] = $name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function reflectParameter(\ReflectionParameter $param): array
    {
        $type = $param->getType();
        $schema = self::schemaForType($type);

        $description = self::readParamDescription($param);
        if ($description !== null) {
            $schema['description'] = $description;
        }

        $enumValues = self::readEnumAttribute($param);
        if ($enumValues !== null) {
            $schema['enum'] = $enumValues;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function schemaForType(?\ReflectionType $type): array
    {
        if ($type === null) {
            return ['type' => 'string'];
        }

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            // For union/intersection, fall back to a permissive schema.
            return ['type' => 'string'];
        }

        /** @var \ReflectionNamedType $type */
        $name = $type->getName();
        $schema = match ($name) {
            'string' => ['type' => 'string'],
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array'],
            default => self::schemaForClass($name),
        };

        if ($type->allowsNull()) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function schemaForClass(string $class): array
    {
        if (!class_exists($class) && !interface_exists($class)) {
            return ['type' => 'string'];
        }

        if (is_subclass_of($class, \BackedEnum::class)) {
            /** @var class-string<\BackedEnum> $class */
            $cases = $class::cases();
            $values = array_map(static fn (\BackedEnum $c) => $c->value, $cases);
            $first = $cases[0] ?? null;
            $base = ($first !== null && is_int($first->value)) ? 'integer' : 'string';

            return ['type' => $base, 'enum' => $values];
        }

        return ['type' => 'object'];
    }

    private static function readParamDescription(\ReflectionParameter $param): ?string
    {
        foreach ($param->getAttributes(Param::class) as $attr) {
            /** @var Param $instance */
            $instance = $attr->newInstance();

            return $instance->description;
        }

        return null;
    }

    /**
     * @return list<string|int|float|bool>|null
     */
    private static function readEnumAttribute(\ReflectionParameter $param): ?array
    {
        foreach ($param->getAttributes(EnumAttribute::class) as $attr) {
            /** @var EnumAttribute $instance */
            $instance = $attr->newInstance();

            return $instance->values;
        }

        return null;
    }
}
