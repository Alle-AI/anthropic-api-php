<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Models;

/**
 * Read-side DTO for an entry from `GET /v1/models`. Exposes the common
 * fields plus a raw escape hatch for anything Anthropic adds later.
 */
final readonly class ModelInfo
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $displayName,
        public ?string $createdAt,
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
            type: isset($raw['type']) && is_string($raw['type']) ? $raw['type'] : 'model',
            displayName: isset($raw['display_name']) && is_string($raw['display_name']) ? $raw['display_name'] : null,
            createdAt: isset($raw['created_at']) && is_string($raw['created_at']) ? $raw['created_at'] : null,
            raw: $raw,
        );
    }

    public function toModel(): Model
    {
        return Model::of($this->id);
    }
}
