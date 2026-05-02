<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Marks a content block for prompt caching.
 *
 * ```php
 * TextBlock::of($longCorpus)->withCacheControl(CacheControl::ephemeral('1h'));
 * ```
 */
final readonly class CacheControl
{
    public function __construct(
        public string $type = 'ephemeral',
        public ?string $ttl = null,
    ) {
    }

    /**
     * @param  '5m'|'1h'|null  $ttl
     */
    public static function ephemeral(?string $ttl = null): self
    {
        return new self(type: 'ephemeral', ttl: $ttl);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $out = ['type' => $this->type];
        if ($this->ttl !== null) {
            $out['ttl'] = $this->ttl;
        }

        return $out;
    }
}
