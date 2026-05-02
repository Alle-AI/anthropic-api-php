<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Auth;

use Psr\Http\Message\RequestInterface;

/**
 * Optional sub-interface for {@see AuthProvider}s that need to transform
 * the entire outgoing request, not just inject headers.
 *
 * The default {@see ApiKeyAuth} only adds an `x-api-key` header — for that,
 * the simpler `authenticate()` interface is enough. But Bedrock and Vertex
 * AI rewrite the URL, mutate the body, and sign the result. Implementing
 * this sub-interface tells {@see \AlleAI\Anthropic\Http\Middleware\AuthMiddleware}
 * to call `apply()` instead, handing over the whole request.
 */
interface RequestTransformingAuthProvider extends AuthProvider
{
    public function apply(RequestInterface $request): RequestInterface;
}
