<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * A single piece of message content (text, image, tool use, etc.).
 *
 * Implementations are immutable readonly classes.
 */
interface ContentBlock
{
    public function type(): string;

    /**
     * Wire-format representation suitable for json_encode.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
