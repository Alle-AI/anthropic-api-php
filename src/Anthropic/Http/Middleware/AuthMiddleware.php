<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http\Middleware;

use AlleAI\Anthropic\Auth\AuthProvider;
use AlleAI\Anthropic\Auth\RequestTransformingAuthProvider;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class AuthMiddleware implements Middleware
{
    public function __construct(private AuthProvider $auth)
    {
    }

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        if ($this->auth instanceof RequestTransformingAuthProvider) {
            return $next($this->auth->apply($request));
        }

        foreach ($this->auth->authenticate($request) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $next($request);
    }
}
