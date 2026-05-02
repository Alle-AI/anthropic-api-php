<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events\Deltas;

/**
 * Partial JSON for a tool-use block's `input` field. Concatenate every
 * partial chunk for the same content-block index and decode the result
 * once `content_block_stop` arrives.
 */
final readonly class InputJsonDelta implements Delta
{
    public function __construct(public string $partialJson) {}

    public function type(): string
    {
        return 'input_json_delta';
    }
}
