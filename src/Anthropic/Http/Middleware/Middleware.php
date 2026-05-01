<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP middleware in onion form: each middleware may modify the request,
 * call $next($request), then modify the response.
 */
interface Middleware
{
    /**
     * @param  callable(RequestInterface): ResponseInterface  $next
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface;
}
