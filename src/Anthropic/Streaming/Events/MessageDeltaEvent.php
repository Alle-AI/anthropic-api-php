<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Messages\Usage;

/**
 * Emitted near the end of a stream. Carries the final stop_reason and the
 * cumulative usage counters.
 */
final readonly class MessageDeltaEvent implements StreamEvent
{
    public function __construct(
        public ?StopReason $stopReason,
        public ?string $stopSequence,
        public ?Usage $usage,
    ) {
    }

    public function type(): string
    {
        return 'message_delta';
    }
}
