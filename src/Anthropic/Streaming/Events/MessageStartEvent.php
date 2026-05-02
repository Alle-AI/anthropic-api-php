<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

/**
 * Emitted once at the start of every stream, carrying the partial message
 * envelope (id, role, model, initial usage). Content blocks arrive in
 * subsequent ContentBlockStart/Delta/Stop triplets.
 */
final readonly class MessageStartEvent implements StreamEvent
{
    /**
     * @param  array<string, mixed>  $message  raw `message` payload
     */
    public function __construct(public array $message) {}

    public function type(): string
    {
        return 'message_start';
    }
}
