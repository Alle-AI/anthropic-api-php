<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

use AlleAI\Anthropic\Streaming\Events\Deltas\Delta;

final readonly class ContentBlockDeltaEvent implements StreamEvent
{
    public function __construct(
        public int $index,
        public Delta $delta,
    ) {}

    public function type(): string
    {
        return 'content_block_delta';
    }
}
