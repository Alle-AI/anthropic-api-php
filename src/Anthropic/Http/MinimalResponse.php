<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Bare-bones PSR-7 ResponseInterface implementation used internally by
 * {@see CurlStreamTransport} to build error exceptions without pulling in
 * a PSR-7 dependency.
 *
 * Read-only surface: `getStatusCode()`, `getBody()`, `getHeaders()`,
 * `getHeaderLine()`, `getHeader()`. Mutators throw — this is not a
 * general-purpose response.
 *
 * @internal
 */
final class MinimalResponse implements ResponseInterface
{
    private readonly StreamInterface $body;
    /** @var array<string, list<string>> headers, lower-cased keys */
    private readonly array $headersLower;
    /** @var array<string, list<string>> headers, original-case keys */
    private readonly array $headers;

    /**
     * @param  array<string, list<string>>  $headers  lower-cased header names
     */
    public function __construct(
        private readonly int $status,
        array $headers,
        string $rawBody,
    ) {
        $this->body = new MinimalStream($rawBody);
        $normalized = [];
        $original = [];
        foreach ($headers as $name => $values) {
            $normalized[strtolower($name)] = array_values($values);
            $original[$name] = array_values($values);
        }
        $this->headersLower = $normalized;
        $this->headers = $original;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getReasonPhrase(): string
    {
        return '';
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        throw new \LogicException('MinimalResponse is immutable.');
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion(string $version): MessageInterface
    {
        throw new \LogicException('MinimalResponse is immutable.');
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headersLower[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        return $this->headersLower[strtolower($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): MessageInterface
    {
        throw new \LogicException('MinimalResponse is immutable.');
    }

    public function withAddedHeader(string $name, $value): MessageInterface
    {
        throw new \LogicException('MinimalResponse is immutable.');
    }

    public function withoutHeader(string $name): MessageInterface
    {
        throw new \LogicException('MinimalResponse is immutable.');
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): MessageInterface
    {
        throw new \LogicException('MinimalResponse is immutable.');
    }
}
