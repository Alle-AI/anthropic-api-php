<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools\Schema\Attributes;

/**
 * Documents a parameter for the JSON-Schema generator. Apply to a
 * `run()` parameter on a {@see \AlleAI\Anthropic\Tools\ClassTool}.
 *
 * ```php
 * public function run(
 *     #[Param('City name, e.g. "Accra"')] string $city,
 * ): array { ... }
 * ```
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final readonly class Param
{
    public function __construct(public string $description)
    {
    }
}
