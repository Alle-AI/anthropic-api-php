<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http\Middleware;

use AlleAI\Anthropic\Exceptions\ConnectionException;
use AlleAI\Anthropic\Exceptions\RequestException;
use AlleAI\Anthropic\Exceptions\TimeoutException;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\RetryPolicy;
use AlleAI\Anthropic\Util\Sleeper;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Retries failed requests according to the configured policy.
 *
 * Retries on:
 *   - configured retryable status codes (default: 408, 409, 429, 500, 502, 503, 504, 529)
 *   - connection errors (RequestException, ConnectionException, TimeoutException)
 *
 * Honors the Retry-After response header when present.
 */
final readonly class RetryMiddleware implements Middleware
{
    public function __construct(
        private RetryPolicy $policy,
        private Sleeper $sleeper,
    ) {}

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $attempt = 0;
        $lastException = null;
        $lastResponse = null;

        while ($attempt < $this->policy->maxAttempts) {
            $attempt++;

            try {
                $response = $next($request);
            } catch (TimeoutException | ConnectionException | RequestException $e) {
                $lastException = $e;
                $lastResponse = null;

                if (!$this->policy->retryOnConnectionError || $attempt >= $this->policy->maxAttempts) {
                    throw $e;
                }

                $this->sleeper->sleep($this->policy->delayFor($attempt));
                continue;
            }

            $status = $response->getStatusCode();
            if (!$this->policy->isRetryableStatus($status) || $attempt >= $this->policy->maxAttempts) {
                return $response;
            }

            $lastResponse = $response;
            $retryAfter = $this->parseRetryAfter($response);
            $this->sleeper->sleep($this->policy->delayFor($attempt, $retryAfter));
        }

        // Exhausted attempts. Re-raise the last error or return the last response.
        if ($lastException !== null) {
            throw $lastException;
        }

        /** @var ResponseInterface $lastResponse */
        return $lastResponse;
    }

    private function parseRetryAfter(ResponseInterface $response): ?int
    {
        $value = $response->getHeaderLine(Headers::RETRY_AFTER);
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
