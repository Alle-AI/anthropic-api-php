<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

use Psr\Http\Message\RequestInterface;

/**
 * Sends a batch of PSR-7 requests in parallel, returning one
 * {@see ConcurrentResult} per input in the same order. Implementations
 * decide the actual concurrency mechanism (libcurl multi-handle by
 * default; tests substitute their own).
 */
interface ConcurrentSender
{
    /**
     * @param  list<RequestInterface>  $requests
     *
     * @return list<ConcurrentResult>
     */
    public function sendAll(array $requests, int $concurrency = 5): array;
}
