<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

use AlleAI\Anthropic\Exceptions\ApiException;
use AlleAI\Anthropic\Exceptions\ConnectionException;
use AlleAI\Anthropic\Exceptions\TimeoutException;

/**
 * One entry in the result list returned by {@see ConcurrentSender::sendAll()}.
 *
 * On success, `$body` and `$status` are set and `$exception` is null.
 * On failure, `$exception` is set; `$body` may also be present (e.g. for
 * a non-2xx response with an error JSON body).
 */
final readonly class ConcurrentResult
{
    public function __construct(
        public ?int $status,
        public ?string $body,
        public ApiException|TimeoutException|ConnectionException|null $exception = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->exception === null && $this->body !== null;
    }
}
