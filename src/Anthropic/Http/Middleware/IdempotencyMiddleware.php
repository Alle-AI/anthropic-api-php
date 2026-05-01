<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http\Middleware;

use AlleAI\Anthropic\Http\Headers;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;

/**
 * Adds anthropic-idempotency-key on POST requests when the caller hasn't set one.
 * The same key survives across retry attempts of the same logical call (the
 * RetryMiddleware reuses the request, so this header travels with it).
 */
final class IdempotencyMiddleware implements Middleware
{
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        if ($request->getMethod() !== 'POST') {
            return $next($request);
        }

        if ($request->hasHeader(Headers::IDEMPOTENCY_KEY)) {
            return $next($request);
        }

        return $next($request->withHeader(Headers::IDEMPOTENCY_KEY, Uuid::uuid7()->toString()));
    }
}
