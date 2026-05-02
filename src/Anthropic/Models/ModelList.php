<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Models;

/**
 * One page of {@see ModelInfo} entries plus pagination cursors from
 * `GET /v1/models`.
 */
final readonly class ModelList
{
    /**
     * @param  list<ModelInfo>  $data
     */
    public function __construct(
        public array $data,
        public bool $hasMore,
        public ?string $firstId,
        public ?string $lastId,
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $data = [];
        if (isset($raw['data']) && is_array($raw['data'])) {
            foreach ($raw['data'] as $entry) {
                if (is_array($entry)) {
                    /** @var array<string, mixed> $entry */
                    $data[] = ModelInfo::fromArray($entry);
                }
            }
        }

        return new self(
            data: $data,
            hasMore: (bool) ($raw['has_more'] ?? false),
            firstId: isset($raw['first_id']) && is_string($raw['first_id']) ? $raw['first_id'] : null,
            lastId: isset($raw['last_id']) && is_string($raw['last_id']) ? $raw['last_id'] : null,
        );
    }
}
