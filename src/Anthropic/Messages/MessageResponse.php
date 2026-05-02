<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages;

use AlleAI\Anthropic\Messages\Content\ContentBlock;
use AlleAI\Anthropic\Messages\Content\ContentBlockFactory;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\ToolUseBlock;

/**
 * Response from `POST /v1/messages`.
 *
 * For the common case of "give me the assistant's text", call ::text().
 * For tool use, ::toolUses() returns the tool_use blocks. The `raw`
 * property holds the original decoded payload for any field the SDK
 * doesn't yet model.
 */
final readonly class MessageResponse
{
    /**
     * @param  list<ContentBlock>  $content
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public Role $role,
        public array $content,
        public string $model,
        public ?StopReason $stopReason,
        public ?string $stopSequence,
        public Usage $usage,
        public array $raw,
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $rawContent = isset($raw['content']) && is_array($raw['content']) ? $raw['content'] : [];
        $blocks = [];
        foreach ($rawContent as $blockRaw) {
            if (is_array($blockRaw)) {
                /** @var array<string, mixed> $blockRaw */
                $blocks[] = ContentBlockFactory::fromArray($blockRaw);
            }
        }

        $usageRaw = isset($raw['usage']) && is_array($raw['usage']) ? $raw['usage'] : [];

        $roleValue = isset($raw['role']) && is_string($raw['role']) ? $raw['role'] : Role::ASSISTANT->value;
        $role = Role::tryFrom($roleValue) ?? Role::ASSISTANT;

        return new self(
            id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : '',
            role: $role,
            content: $blocks,
            model: isset($raw['model']) && is_string($raw['model']) ? $raw['model'] : '',
            stopReason: StopReason::tryFromString(
                isset($raw['stop_reason']) && is_string($raw['stop_reason']) ? $raw['stop_reason'] : null,
            ),
            stopSequence: isset($raw['stop_sequence']) && is_string($raw['stop_sequence'])
                ? $raw['stop_sequence']
                : null,
            usage: Usage::fromArray($usageRaw),
            raw: $raw,
        );
    }

    /**
     * Concatenated text from all TextBlocks in the response.
     */
    public function text(): string
    {
        $parts = [];
        foreach ($this->content as $block) {
            if ($block instanceof TextBlock) {
                $parts[] = $block->text;
            }
        }

        return implode('', $parts);
    }

    /**
     * @return list<ToolUseBlock>
     */
    public function toolUses(): array
    {
        return array_values(array_filter(
            $this->content,
            static fn (ContentBlock $block): bool => $block instanceof ToolUseBlock,
        ));
    }

    public function hasToolUse(): bool
    {
        return $this->stopReason === StopReason::TOOL_USE || count($this->toolUses()) > 0;
    }
}
