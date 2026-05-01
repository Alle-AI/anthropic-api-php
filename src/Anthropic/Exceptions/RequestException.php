<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

use Psr\Http\Message\RequestInterface;

/**
 * Thrown for transport/network errors (DNS failure, connection refused, TLS, etc.)
 * before an HTTP response was received.
 */
class RequestException extends AnthropicException
{
    public function __construct(
        string $message,
        public readonly ?RequestInterface $request = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
