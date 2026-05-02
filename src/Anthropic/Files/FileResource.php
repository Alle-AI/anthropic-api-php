<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Files;

/**
 * Metadata returned by the Files API for an uploaded file.
 */
final readonly class FileResource
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $filename,
        public string $mimeType,
        public int $sizeBytes,
        public ?string $createdAt,
        public ?string $type,
        public bool $downloadable,
        public array $raw,
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : '',
            filename: isset($raw['filename']) && is_string($raw['filename']) ? $raw['filename'] : '',
            mimeType: isset($raw['mime_type']) && is_string($raw['mime_type']) ? $raw['mime_type'] : '',
            sizeBytes: isset($raw['size_bytes']) && is_numeric($raw['size_bytes']) ? (int) $raw['size_bytes'] : 0,
            createdAt: isset($raw['created_at']) && is_string($raw['created_at']) ? $raw['created_at'] : null,
            type: isset($raw['type']) && is_string($raw['type']) ? $raw['type'] : null,
            downloadable: (bool) ($raw['downloadable'] ?? false),
            raw: $raw,
        );
    }
}
