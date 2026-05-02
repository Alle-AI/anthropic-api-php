<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events\Deltas;

final readonly class TextDelta implements Delta
{
    public function __construct(public string $text)
    {
    }

    public function type(): string
    {
        return 'text_delta';
    }
}
