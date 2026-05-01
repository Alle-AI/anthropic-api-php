<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/** HTTP 429 — rate limit exceeded. Inspect ::retryAfter() for when to try again. */
final class RateLimitException extends ApiException
{
    /**
     * Seconds to wait before retrying, parsed from the Retry-After header
     * (which Anthropic sends as a delta-seconds integer).
     */
    public function retryAfter(): ?int
    {
        $value = $this->header('Retry-After');

        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        // HTTP-date format fallback
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
