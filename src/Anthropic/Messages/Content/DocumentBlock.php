<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * A document content block — the canonical way to attach a file (PDF,
 * text, etc.) to a Messages request.
 *
 * Build one of three sources:
 *   - {@see DocumentBlock::fromFileId()}   referencing a Files API upload
 *   - {@see DocumentBlock::fromUrl()}      pointing at a public URL
 *   - {@see DocumentBlock::fromBase64()}   inline base64-encoded contents
 *
 * Citations can be enabled by setting `$citations = ['enabled' => true]`
 * on the wire — pass via the `extras` constructor argument.
 */
final readonly class DocumentBlock implements ContentBlock
{
    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $extras  any additional fields (citations, title, context)
     */
    public function __construct(
        public array $source,
        public ?CacheControl $cacheControl = null,
        public array $extras = [],
    ) {
    }

    public static function fromFileId(string $fileId): self
    {
        return new self(['type' => 'file', 'file_id' => $fileId]);
    }

    public static function fromUrl(string $url): self
    {
        return new self(['type' => 'url', 'url' => $url]);
    }

    public static function fromBase64(string $base64, string $mediaType = 'application/pdf'): self
    {
        return new self(['type' => 'base64', 'media_type' => $mediaType, 'data' => $base64]);
    }

    public function withCacheControl(CacheControl $cacheControl): self
    {
        return new self($this->source, $cacheControl, $this->extras);
    }

    public function withCitationsEnabled(bool $enabled = true): self
    {
        $extras = $this->extras;
        $extras['citations'] = ['enabled' => $enabled];

        return new self($this->source, $this->cacheControl, $extras);
    }

    public function type(): string
    {
        return 'document';
    }

    public function toArray(): array
    {
        $out = ['type' => 'document', 'source' => $this->source];
        foreach ($this->extras as $k => $v) {
            $out[$k] = $v;
        }
        if ($this->cacheControl !== null) {
            $out['cache_control'] = $this->cacheControl->toArray();
        }

        return $out;
    }
}
