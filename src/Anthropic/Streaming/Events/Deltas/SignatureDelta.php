<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events\Deltas;

/**
 * Cryptographic signature for an extended-thinking block. Echo it back
 * unchanged in subsequent turns to keep multi-turn thinking valid.
 */
final readonly class SignatureDelta implements Delta
{
    public function __construct(public string $signature) {}

    public function type(): string
    {
        return 'signature_delta';
    }
}
