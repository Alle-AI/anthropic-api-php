<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools\Schema\Attributes;

/**
 * Restricts a parameter to a fixed set of values in the generated schema.
 *
 * Backed PHP enums are detected automatically; use this attribute when a
 * scalar parameter should be constrained without introducing an enum type.
 *
 * ```php
 * #[Enum('c', 'f')] string $units = 'c'
 * ```
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Enum
{
    /** @var list<string|int|float|bool> */
    public array $values;

    public function __construct(string|int|float|bool ...$values)
    {
        $this->values = array_values($values);
    }
}
