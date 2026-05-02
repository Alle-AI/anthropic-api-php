<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

/**
 * Sealed-style interface implemented by every typed SSE event.
 *
 * Inspect events with `instanceof` checks:
 *
 * ```php
 * foreach ($stream as $event) {
 *     if ($event instanceof ContentBlockDeltaEvent
 *         && $event->delta instanceof Deltas\TextDelta
 *     ) {
 *         echo $event->delta->text;
 *     }
 * }
 * ```
 */
interface StreamEvent
{
    public function type(): string;
}
