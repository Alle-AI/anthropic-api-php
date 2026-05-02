<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

final readonly class ContentBlockStopEvent implements StreamEvent
{
    public function __construct(public int $index) {}

    public function type(): string
    {
        return 'content_block_stop';
    }
}
