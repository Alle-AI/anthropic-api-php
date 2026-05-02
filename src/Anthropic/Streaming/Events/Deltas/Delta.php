<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events\Deltas;

/**
 * Sealed-style interface for the `delta` payload of a ContentBlockDeltaEvent.
 */
interface Delta
{
    public function type(): string;
}
