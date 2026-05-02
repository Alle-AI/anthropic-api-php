<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Batches;

/**
 * Metadata for a Message Batch.
 */
final readonly class BatchResponse
{
    /**
     * @param  array<string, int>  $requestCounts
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public BatchStatus $processingStatus,
        public array $requestCounts,
        public ?string $endedAt,
        public ?string $createdAt,
        public ?string $expiresAt,
        public ?string $resultsUrl,
        public array $raw,
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $statusStr = isset($raw['processing_status']) && is_string($raw['processing_status'])
            ? $raw['processing_status']
            : null;

        $counts = [];
        if (isset($raw['request_counts']) && is_array($raw['request_counts'])) {
            foreach ($raw['request_counts'] as $k => $v) {
                if (is_string($k) && is_numeric($v)) {
                    $counts[$k] = (int) $v;
                }
            }
        }

        return new self(
            id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : '',
            processingStatus: BatchStatus::tryFromString($statusStr) ?? BatchStatus::IN_PROGRESS,
            requestCounts: $counts,
            endedAt: isset($raw['ended_at']) && is_string($raw['ended_at']) ? $raw['ended_at'] : null,
            createdAt: isset($raw['created_at']) && is_string($raw['created_at']) ? $raw['created_at'] : null,
            expiresAt: isset($raw['expires_at']) && is_string($raw['expires_at']) ? $raw['expires_at'] : null,
            resultsUrl: isset($raw['results_url']) && is_string($raw['results_url']) ? $raw['results_url'] : null,
            raw: $raw,
        );
    }

    public function isComplete(): bool
    {
        return $this->processingStatus->isTerminal();
    }
}
