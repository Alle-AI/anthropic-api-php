<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * A single citation attached to a text block, pointing back to a span of
 * source text in a referenced document.
 *
 * Anthropic supports several citation shapes — `char_location`,
 * `page_location`, `content_block_location`, and `web_search_result_location`.
 * The DTO exposes the common fields and stashes the original payload in
 * `$raw` for any field the SDK doesn't yet model.
 */
final readonly class Citation
{
    /**
     * @param  array<string, mixed>  $raw  full original payload
     */
    public function __construct(
        public string $type,
        public ?string $citedText,
        public ?int $documentIndex,
        public ?string $documentTitle,
        public array $raw,
    ) {
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            type: isset($raw['type']) && is_string($raw['type']) ? $raw['type'] : '',
            citedText: isset($raw['cited_text']) && is_string($raw['cited_text']) ? $raw['cited_text'] : null,
            documentIndex: isset($raw['document_index']) && is_numeric($raw['document_index'])
                ? (int) $raw['document_index']
                : null,
            documentTitle: isset($raw['document_title']) && is_string($raw['document_title'])
                ? $raw['document_title']
                : null,
            raw: $raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw !== [] ? $this->raw : [
            'type' => $this->type,
            'cited_text' => $this->citedText,
            'document_index' => $this->documentIndex,
            'document_title' => $this->documentTitle,
        ];
    }
}
