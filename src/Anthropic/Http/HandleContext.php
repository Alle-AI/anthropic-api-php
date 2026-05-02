<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

/**
 * Per-handle scratch space used by {@see ConcurrentTransport} to capture
 * the response status and headers from cURL's HEADERFUNCTION callback.
 *
 * @internal
 */
final class HandleContext
{
    public int $statusCode = 0;

    /** @var array<string, list<string>> */
    public array $headers = [];
}
