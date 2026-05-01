<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Auth;

use Psr\Http\Message\RequestInterface;

/**
 * Sends `Authorization: Bearer <token>`. Used by Vertex (post-token-exchange)
 * and Console OAuth flows. Wrap a refresh callback to rotate tokens lazily.
 */
final class BearerAuth implements AuthProvider
{
    /** @var \Closure(): string */
    private \Closure $tokenProvider;

    /**
     * @param  string|callable(): string  $token  literal token or a callable that returns one (called per request)
     */
    public function __construct(string|callable $token)
    {
        $this->tokenProvider = is_callable($token)
            ? \Closure::fromCallable($token)
            : static fn (): string => $token;
    }

    public function authenticate(RequestInterface $request): array
    {
        $token = ($this->tokenProvider)();
        if (trim($token) === '') {
            throw new \RuntimeException('BearerAuth produced an empty token.');
        }

        return ['Authorization' => 'Bearer ' . $token];
    }

    public function baseUrl(?string $configured): ?string
    {
        return $configured;
    }
}
