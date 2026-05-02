<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Fallback block for content types the SDK doesn't yet model.
 *
 * Always inspectable via {@see UnknownBlock::$raw} so newer features work
 * even before the SDK ships first-class support.
 */
final readonly class UnknownBlock implements ContentBlock
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $blockType,
        public array $raw,
    ) {
    }

    public function type(): string
    {
        return $this->blockType;
    }

    public function toArray(): array
    {
        return $this->raw;
    }
}
