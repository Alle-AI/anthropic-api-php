<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

/**
 * In-stream error frame. The {@see EventStream} surfaces this by throwing
 * a StreamException — most callers will never observe an `ErrorEvent`
 * directly, but it's available for advanced parser consumers.
 */
final readonly class ErrorEvent implements StreamEvent
{
    public function __construct(
        public string $errorType,
        public string $message,
    ) {}

    public function type(): string
    {
        return 'error';
    }
}
