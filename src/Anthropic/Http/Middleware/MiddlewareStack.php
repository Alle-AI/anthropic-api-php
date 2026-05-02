<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http\Middleware;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Composes a list of middleware around a final handler in onion fashion.
 *
 * The first middleware in the list is the outermost layer — it sees the
 * request first and the response last.
 */
final class MiddlewareStack
{
    /** @var list<Middleware> */
    private array $middleware = [];

    /** @param  callable(RequestInterface): ResponseInterface  $handler */
    public function __construct(private $handler)
    {
    }

    public function push(Middleware $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    public function handle(RequestInterface $request): ResponseInterface
    {
        $next = $this->handler;

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = static fn (RequestInterface $req): ResponseInterface => $middleware->handle($req, $next);
        }

        return $next($request);
    }
}
