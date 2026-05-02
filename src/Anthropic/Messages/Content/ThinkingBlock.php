<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Extended-thinking block emitted by reasoning-enabled models. The
 * `signature` must be preserved verbatim and echoed back in subsequent
 * turns to keep multi-turn thinking valid.
 */
final readonly class ThinkingBlock implements ContentBlock
{
    public function __construct(
        public string $thinking,
        public ?string $signature = null,
    ) {
    }

    public function type(): string
    {
        return 'thinking';
    }

    public function toArray(): array
    {
        $out = ['type' => 'thinking', 'thinking' => $this->thinking];
        if ($this->signature !== null) {
            $out['signature'] = $this->signature;
        }

        return $out;
    }
}
