<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

final readonly class TextBlock implements ContentBlock
{
    /**
     * @param  list<Citation>  $citations
     */
    public function __construct(
        public string $text,
        public ?CacheControl $cacheControl = null,
        public array $citations = [],
    ) {
    }

    public static function of(string $text): self
    {
        return new self($text);
    }

    public function withCacheControl(CacheControl $cacheControl): self
    {
        return new self($this->text, $cacheControl, $this->citations);
    }

    /**
     * @param  list<Citation>  $citations
     */
    public function withCitations(array $citations): self
    {
        return new self($this->text, $this->cacheControl, $citations);
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
        if ($this->citations !== []) {
            $out['citations'] = array_map(static fn (Citation $c): array => $c->toArray(), $this->citations);
        }

        return $out;
    }
}
