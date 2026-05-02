<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events\Deltas;

/**
 * Fallback for delta types the SDK doesn't yet model. The raw payload is
 * always available for inspection via {@see UnknownDelta::$raw}.
 */
final readonly class UnknownDelta implements Delta
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $deltaType,
        public array $raw,
    ) {}

    public function type(): string
    {
        return $this->deltaType;
    }
}
