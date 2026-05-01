<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

use AlleAI\Anthropic\Exceptions\ConnectionException;
use AlleAI\Anthropic\Exceptions\ExceptionFactory;
use AlleAI\Anthropic\Exceptions\RequestException;
use AlleAI\Anthropic\Http\Middleware\Middleware;
use AlleAI\Anthropic\Http\Middleware\MiddlewareStack;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Default Transport implementation that delegates to a PSR-18 HTTP client
 * after running the request through a middleware stack.
 *
 * Maps PSR-18 client exceptions to the SDK exception hierarchy and
 * automatically converts non-2xx responses into {@see \AlleAI\Anthropic\Exceptions\ApiException}.
 */
final class Psr18Transport implements Transport
{
    private MiddlewareStack $stack;

    /**
     * @param  list<Middleware>  $middleware  outermost-first
     */
    public function __construct(
        private readonly ClientInterface $client,
        array $middleware = [],
    ) {
        $this->stack = new MiddlewareStack(fn (RequestInterface $r): ResponseInterface => $this->dispatch($r));
        foreach ($middleware as $m) {
            $this->stack->push($m);
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->stack->handle($request);

        if ($response->getStatusCode() >= 400) {
            throw ExceptionFactory::fromResponse($response, $request);
        }

        return $response;
    }

    /**
     * Final handler — invokes the PSR-18 client and translates
     * its exceptions into ours.
     */
    private function dispatch(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (NetworkExceptionInterface $e) {
            throw new ConnectionException($e->getMessage(), $request, $e);
        } catch (RequestExceptionInterface $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        } catch (ClientExceptionInterface $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        }
    }
}
