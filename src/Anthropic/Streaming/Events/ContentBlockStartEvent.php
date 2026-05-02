<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events;

use AlleAI\Anthropic\Messages\Content\ContentBlock;

final readonly class ContentBlockStartEvent implements StreamEvent
{
    public function __construct(
        public int $index,
        public ContentBlock $contentBlock,
    ) {
    }

    public function type(): string
    {
        return 'content_block_start';
    }
}
