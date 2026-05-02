<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

/**
 * Fallback for SSE event types the SDK doesn't yet model.
 */
final readonly class UnknownEvent implements StreamEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $eventType,
        public array $raw,
    ) {
    }

    public function type(): string
    {
        return $this->eventType;
    }
}
