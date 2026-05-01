<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * User-supplied result for a previous ToolUseBlock.
 *
 * `$content` may be a string, an array of content blocks (typically text or
 * image blocks for richer results), or any JSON-serializable value.
 */
final readonly class ToolResultBlock implements ContentBlock
{
    public function __construct(
        public string $toolUseId,
        public mixed $content,
        public bool $isError = false,
        public ?CacheControl $cacheControl = null,
    ) {}

    public static function ok(string $toolUseId, mixed $content): self
    {
        return new self($toolUseId, $content, false);
    }

    public static function error(string $toolUseId, string $message): self
    {
        return new self($toolUseId, $message, true);
    }

    public function type(): string
    {
        return 'tool_result';
    }

    public function toArray(): array
    {
        $content = $this->content;
        if (is_array($content)) {
            $content = array_map(
                static fn (mixed $item): mixed => $item instanceof ContentBlock ? $item->toArray() : $item,
                $content,
            );
        }

        $out = [
            'type' => 'tool_result',
            'tool_use_id' => $this->toolUseId,
            'content' => $content,
        ];

        if ($this->isError) {
            $out['is_error'] = true;
        }

        if ($this->cacheControl !== null) {
            $out['cache_control'] = $this->cacheControl->toArray();
        }

        return $out;
    }
}
