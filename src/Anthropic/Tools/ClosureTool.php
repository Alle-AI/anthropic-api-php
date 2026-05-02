<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools;

use AlleAI\Anthropic\Messages\Content\CacheControl;

/**
 * A Tool defined inline with a closure handler.
 *
 * ```php
 * $weather = ClosureTool::create(
 *     name: 'get_weather',
 *     description: 'Current weather for a city',
 *     handler: fn(array $i) => fetchWeather($i['city']),
 *     schema: [
 *         'type' => 'object',
 *         'properties' => ['city' => ['type' => 'string']],
 *         'required' => ['city'],
 *     ],
 * );
 * ```
 */
final readonly class ClosureTool implements Tool
{
    /** @var \Closure(array<string, mixed>): mixed */
    private \Closure $handler;

    /**
     * @param  array<string, mixed>  $schema
     * @param  \Closure(array<string, mixed>): mixed  $handler
     */
    public function __construct(
        private string $name,
        private string $description,
        private array $schema,
        \Closure $handler,
        private ?CacheControl $cacheControl = null,
    ) {
        $this->handler = $handler;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  callable(array<string, mixed>): mixed  $handler
     */
    public static function create(
        string $name,
        string $description,
        array $schema,
        callable $handler,
        ?CacheControl $cacheControl = null,
    ): self {
        return new self($name, $description, $schema, \Closure::fromCallable($handler), $cacheControl);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function inputSchema(): array
    {
        return $this->schema;
    }

    public function run(array $input): mixed
    {
        return ($this->handler)($input);
    }

    public function cacheControl(): ?CacheControl
    {
        return $this->cacheControl;
    }

    public function toArray(): array
    {
        $out = [
            'name' => $this->name,
            'description' => $this->description,
            'input_schema' => $this->schema,
        ];

        if ($this->cacheControl !== null) {
            $out['cache_control'] = $this->cacheControl->toArray();
        }

        return $out;
    }
}
