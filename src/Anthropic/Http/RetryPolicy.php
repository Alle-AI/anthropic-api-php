<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

/**
 * Configuration for automatic retries with exponential backoff.
 */
final readonly class RetryPolicy
{
    /**
     * @param  list<int>  $retryableStatuses
     */
    public function __construct(
        public int $maxAttempts = 3,
        public float $baseDelay = 0.5,
        public float $maxDelay = 30.0,
        public float $jitter = 0.25,
        public array $retryableStatuses = [408, 409, 429, 500, 502, 503, 504, 529],
        public bool $honorRetryAfter = true,
        public bool $retryOnConnectionError = true,
    ) {
    }

    public static function disabled(): self
    {
        return new self(maxAttempts: 1);
    }

    /**
     * Compute the delay before the next attempt.
     *
     * @param  int  $attempt  1-indexed attempt number that just failed
     */
    public function delayFor(int $attempt, ?int $retryAfterSeconds = null): float
    {
        if ($this->honorRetryAfter && $retryAfterSeconds !== null && $retryAfterSeconds >= 0) {
            return min((float) $retryAfterSeconds, $this->maxDelay);
        }

        $exponential = $this->baseDelay * (2 ** ($attempt - 1));
        $delay = min($exponential, $this->maxDelay);

        if ($this->jitter > 0) {
            $jitterAmount = $delay * $this->jitter;
            $delay += (mt_rand(-1_000_000, 1_000_000) / 1_000_000.0) * $jitterAmount;
        }

        return max(0.0, $delay);
    }

    public function isRetryableStatus(int $status): bool
    {
        return in_array($status, $this->retryableStatuses, true);
    }
}
