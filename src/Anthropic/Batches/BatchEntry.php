<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Batches;

/**
 * One request inside a batch. The `customId` is your stable handle —
 * it round-trips through Anthropic and appears on the corresponding
 * result so you can correlate inputs with outputs.
 */
final readonly class BatchEntry
{
    /**
     * @param  array<string, mixed>  $params  Messages create params (model, max_tokens, messages, ...)
     */
    public function __construct(
        public string $customId,
        public array $params,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'custom_id' => $this->customId,
            'params' => $this->params,
        ];
    }
}
