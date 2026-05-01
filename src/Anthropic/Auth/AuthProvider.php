<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Auth;

use Psr\Http\Message\RequestInterface;

/**
 * Pluggable authentication strategy.
 *
 * Implementations must be stateless and side-effect free per call —
 * the same request may be authenticated multiple times during retries.
 */
interface AuthProvider
{
    /**
     * Returns headers to merge into the outgoing request.
     *
     * @return array<string, string>
     */
    public function authenticate(RequestInterface $request): array;

    /**
     * Optional override for the API base URL (e.g. Bedrock/Vertex regional endpoints).
     */
    public function baseUrl(?string $configured): ?string;
}
