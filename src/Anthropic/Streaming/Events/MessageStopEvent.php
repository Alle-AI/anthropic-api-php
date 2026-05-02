<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

final readonly class MessageStopEvent implements StreamEvent
{
    public function type(): string
    {
        return 'message_stop';
    }
}
