<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Batches;

use AlleAI\Anthropic\Messages\MessageResponse;

/**
 * One line of a JSONL batch results stream — pairs the original
 * `custom_id` with either a successful MessageResponse or an error payload.
 */
final readonly class BatchResult
{
    /**
     * @param  array<array-key, mixed>  $raw
     * @param  array<string, mixed>|null  $errorPayload
     */
    public function __construct(
        public string $customId,
        public string $resultType,
        public ?MessageResponse $message,
        public ?array $errorPayload,
        public array $raw,
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $customId = isset($raw['custom_id']) && is_string($raw['custom_id']) ? $raw['custom_id'] : '';
        $resultPayload = isset($raw['result']) && is_array($raw['result']) ? $raw['result'] : [];
        $resultType = isset($resultPayload['type']) && is_string($resultPayload['type'])
            ? $resultPayload['type']
            : 'unknown';

        $message = null;
        $errorPayload = null;

        if ($resultType === 'succeeded') {
            $msg = isset($resultPayload['message']) && is_array($resultPayload['message'])
                ? $resultPayload['message']
                : null;
            if ($msg !== null) {
                /** @var array<array-key, mixed> $msg */
                $message = MessageResponse::fromArray($msg);
            }
        } elseif (isset($resultPayload['error']) && is_array($resultPayload['error'])) {
            /** @var array<string, mixed> $errorPayload */
            $errorPayload = $resultPayload['error'];
        }

        return new self(
            customId: $customId,
            resultType: $resultType,
            message: $message,
            errorPayload: $errorPayload,
            raw: $raw,
        );
    }

    public function succeeded(): bool
    {
        return $this->resultType === 'succeeded' && $this->message !== null;
    }
}
