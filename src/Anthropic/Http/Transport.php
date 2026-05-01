<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Sends an HTTP request through the configured middleware stack to the
 * underlying PSR-18 client (or a streaming cURL transport for SSE).
 *
 * Streaming is intentionally not part of this interface; SSE uses
 * {@see CurlStreamTransport} directly because PSR-18 cannot stream
 * a response body chunk-by-chunk.
 */
interface Transport
{
    public function sendRequest(RequestInterface $request): ResponseInterface;
}
