<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http\Middleware;

use AlleAI\Anthropic\Http\Headers;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Optional PSR-3 logging middleware. Adds one log entry on the way out
 * (the request) and one on the way back (the response or error), tagged
 * with the same correlation id so log scrapers can stitch them together.
 *
 * Bodies are NOT logged by default — they may contain user PII or model
 * outputs. Enable with `logBodies: true` for debugging only.
 *
 * Wire via {@see \AlleAI\Anthropic\ClientBuilder::withLogger()}.
 */
final readonly class LoggingMiddleware implements Middleware
{
    public function __construct(
        private LoggerInterface $logger,
        private string $level = LogLevel::INFO,
        private bool $logBodies = false,
    ) {
    }

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $correlationId = bin2hex(random_bytes(8));
        $started = microtime(true);

        $this->logger->log($this->level, 'anthropic request', [
            'correlation_id' => $correlationId,
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'idempotency_key' => self::nullIfEmpty($request->getHeaderLine(Headers::IDEMPOTENCY_KEY)),
            'body_bytes' => $request->getBody()->getSize(),
            'body' => $this->logBodies ? (string) $request->getBody() : null,
        ]);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->logger->error('anthropic request failed', [
                'correlation_id' => $correlationId,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->logger->log($this->level, 'anthropic response', [
            'correlation_id' => $correlationId,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'status' => $response->getStatusCode(),
            'request_id' => self::nullIfEmpty($response->getHeaderLine(Headers::X_REQUEST_ID)),
            'body_bytes' => $response->getBody()->getSize(),
        ]);

        return $response;
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
