<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Test double for PSR-18 — captures every request and replies with
 * pre-programmed responses.
 */
final class FakePsr18Client implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface> */
    private array $responses = [];

    private readonly Psr17Factory $factory;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @param  array<string, string>  $headers
     */
    public function pushJsonResponse(int $status, array $body, array $headers = []): self
    {
        $response = $this->factory->createResponse($status)
            ->withBody($this->factory->createStream(json_encode($body, JSON_THROW_ON_ERROR)))
            ->withHeader('content-type', 'application/json');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $this->responses[] = $response;

        return $this;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function pushRawResponse(int $status, string $body, array $headers = []): self
    {
        $response = $this->factory->createResponse($status)
            ->withBody($this->factory->createStream($body));
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        $this->responses[] = $response;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->responses === []) {
            throw new \LogicException('FakePsr18Client received a request but has no programmed responses left.');
        }

        return array_shift($this->responses);
    }

    public function lastRequest(): RequestInterface
    {
        if ($this->requests === []) {
            throw new \LogicException('No request was captured.');
        }

        return $this->requests[count($this->requests) - 1];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function lastRequestBody(): array
    {
        $body = (string) $this->lastRequest()->getBody();
        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
