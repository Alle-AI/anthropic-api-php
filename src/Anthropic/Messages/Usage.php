<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages;

/**
 * Token usage breakdown returned with every Messages response.
 *
 * `cacheCreationInputTokens` and `cacheReadInputTokens` are populated
 * when the request used `cache_control` blocks.
 */
final readonly class Usage
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public ?int $cacheCreationInputTokens = null,
        public ?int $cacheReadInputTokens = null,
        public ?string $serviceTier = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            inputTokens: (int) ($raw['input_tokens'] ?? 0),
            outputTokens: (int) ($raw['output_tokens'] ?? 0),
            cacheCreationInputTokens: isset($raw['cache_creation_input_tokens'])
                ? (int) $raw['cache_creation_input_tokens']
                : null,
            cacheReadInputTokens: isset($raw['cache_read_input_tokens'])
                ? (int) $raw['cache_read_input_tokens']
                : null,
            serviceTier: isset($raw['service_tier']) && is_string($raw['service_tier'])
                ? $raw['service_tier']
                : null,
        );
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
