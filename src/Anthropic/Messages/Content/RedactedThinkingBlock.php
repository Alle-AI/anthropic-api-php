<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Opaque, server-redacted thinking block. Echo it back unchanged in
 * follow-up turns — never inspect or modify the data field.
 */
final readonly class RedactedThinkingBlock implements ContentBlock
{
    public function __construct(public string $data) {}

    public function type(): string
    {
        return 'redacted_thinking';
    }

    public function toArray(): array
    {
        return ['type' => 'redacted_thinking', 'data' => $this->data];
    }
}
