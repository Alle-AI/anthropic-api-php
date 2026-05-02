<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events\Deltas;

final readonly class ThinkingDelta implements Delta
{
    public function __construct(public string $thinking) {}

    public function type(): string
    {
        return 'thinking_delta';
    }
}
