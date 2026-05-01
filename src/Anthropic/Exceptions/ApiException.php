<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Thrown when the Anthropic API returns a non-2xx HTTP response.
 *
 * Subclasses are mapped from status codes by ExceptionFactory.
 */
class ApiException extends AnthropicException
{
    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $errorType,
        public readonly ?string $requestId,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly ?RequestInterface $request = null,
        public readonly ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        $name = strtolower($name);
        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $name) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
