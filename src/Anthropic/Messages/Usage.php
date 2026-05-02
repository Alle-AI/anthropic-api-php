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
    ) {
    }

    /**
     * @param  array<array-key, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            inputTokens: self::intOrZero($raw['input_tokens'] ?? null),
            outputTokens: self::intOrZero($raw['output_tokens'] ?? null),
            cacheCreationInputTokens: self::intOrNull($raw['cache_creation_input_tokens'] ?? null),
            cacheReadInputTokens: self::intOrNull($raw['cache_read_input_tokens'] ?? null),
            serviceTier: isset($raw['service_tier']) && is_string($raw['service_tier'])
                ? $raw['service_tier']
                : null,
        );
    }

    private static function intOrZero(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
