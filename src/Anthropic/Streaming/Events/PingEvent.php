<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

/**
 * Periodic keep-alive ping. Safe to ignore in most consumers.
 */
final readonly class PingEvent implements StreamEvent
{
    public function type(): string
    {
        return 'ping';
    }
}
