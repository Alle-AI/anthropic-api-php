<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

final readonly class TextBlock implements ContentBlock
{
    public function __construct(
        public string $text,
        public ?CacheControl $cacheControl = null,
    ) {}

    public static function of(string $text): self
    {
        return new self($text);
    }

    public function withCacheControl(CacheControl $cacheControl): self
    {
        return new self($this->text, $cacheControl);
    }

    public function type(): string
    {
        return 'text';
    }

    public function toArray(): array
    {
        $out = ['type' => 'text', 'text' => $this->text];
        if ($this->cacheControl !== null) {
            $out['cache_control'] = $this->cacheControl->toArray();
        }

        return $out;
    }
}
