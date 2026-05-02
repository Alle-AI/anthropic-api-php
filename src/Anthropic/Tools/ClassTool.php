<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools;

use AlleAI\Anthropic\Messages\Content\CacheControl;
use AlleAI\Anthropic\Tools\Schema\SchemaGenerator;

/**
 * Base class for class-based tools. Subclasses implement `runTool()` and
 * the input schema is generated from its parameters automatically.
 *
 * ```php
 * final class GetWeather extends ClassTool {
 *     public function name(): string { return 'get_weather'; }
 *     public function description(): string { return 'Current weather'; }
 *
 *     protected function runTool(
 *         #[Param('City name')] string $city,
 *         #[Param('Units')] #[Enum('c', 'f')] string $units = 'c',
 *     ): array {
 *         return ['temp' => 22, 'units' => $units];
 *     }
 * }
 * ```
 */
abstract class ClassTool implements Tool
{
    /** @var array<string, mixed>|null */
    private ?array $cachedSchema = null;

    abstract public function name(): string;

    abstract public function description(): string;

    public function inputSchema(): array
    {
        $this->ensureRunToolDeclared();

        return $this->cachedSchema ??= SchemaGenerator::fromMethod(static::class, 'runTool');
    }

    public function run(array $input): mixed
    {
        $this->ensureRunToolDeclared();
        $reflection = new \ReflectionMethod(static::class, 'runTool');
        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $input)) {
                $args[] = $this->coerce($input[$name], $param);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            if ($param->getType()?->allowsNull() === true) {
                $args[] = null;
                continue;
            }

            throw new \InvalidArgumentException(sprintf(
                'Missing required tool input "%s" for tool %s.',
                $name,
                $this->name(),
            ));
        }

        return $reflection->invokeArgs($this, $args);
    }

    /**
     * Coerce a JSON-decoded input value to the parameter's PHP type.
     */
    private function coerce(mixed $value, \ReflectionParameter $param): mixed
    {
        $type = $param->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        if ($value === null && $type->allowsNull()) {
            return null;
        }

        $name = $type->getName();
        if (in_array($name, ['string', 'int', 'float', 'bool', 'array'], true)) {
            return match ($name) {
                'string' => is_scalar($value) ? (string) $value : $value,
                'int' => is_numeric($value) ? (int) $value : $value,
                'float' => is_numeric($value) ? (float) $value : $value,
                'bool' => is_bool($value) ? $value : (bool) $value,
                'array' => is_array($value) ? $value : [$value],
                default => $value,
            };
        }

        if (is_subclass_of($name, \BackedEnum::class) && (is_string($value) || is_int($value))) {
            /** @var class-string<\BackedEnum> $name */
            return $name::tryFrom($value) ?? $value;
        }

        return $value;
    }

    public function cacheControl(): ?CacheControl
    {
        return null;
    }

    public function toArray(): array
    {
        $out = [
            'name' => $this->name(),
            'description' => $this->description(),
            'input_schema' => $this->inputSchema(),
        ];

        $cc = $this->cacheControl();
        if ($cc !== null) {
            $out['cache_control'] = $cc->toArray();
        }

        return $out;
    }

    private function ensureRunToolDeclared(): void
    {
        if (!method_exists(static::class, 'runTool')) {
            throw new \LogicException(sprintf(
                '%s extends ClassTool but does not declare a runTool() method. '
                . 'Implement protected function runTool(...): mixed with typed parameters '
                . '(use #[Param] / #[Enum] attributes to refine the generated JSON Schema).',
                static::class,
            ));
        }
    }

    // Subclasses implement: protected function runTool(...): mixed
    // Signature is per-tool — the JSON Schema is reflected from this method's parameters.
}
