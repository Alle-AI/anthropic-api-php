<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

use AlleAI\Anthropic\Auth\AuthProvider;
use AlleAI\Anthropic\Exceptions\ApiException;
use AlleAI\Anthropic\Exceptions\ConnectionException;
use AlleAI\Anthropic\Exceptions\ExceptionFactory;
use AlleAI\Anthropic\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;

/**
 * SSE-capable HTTP transport built directly on libcurl.
 *
 * PSR-18's `sendRequest()` returns a fully-realized response, which can't
 * stream a server-sent-events body chunk-by-chunk. This transport uses
 * `CURLOPT_WRITEFUNCTION` to push every chunk into a queue that a Generator
 * yields lazily.
 *
 * This is intentionally only used for streaming requests; non-stream calls
 * keep going through {@see Psr18Transport} so users can plug in any
 * PSR-18 client.
 */
final class CurlStreamTransport
{
    public function __construct(
        private readonly AuthProvider $auth,
        private readonly ?string $userAgent = null,
        private readonly float $connectTimeout = 30.0,
        private readonly float $totalTimeout = 600.0,
    ) {}

    /**
     * Send `$request` and yield each raw SSE chunk as it arrives.
     *
     * Pre-stream errors (connect/auth/HTTP 4xx/5xx) are thrown as soon as
     * the response status becomes available. Once streaming starts, each
     * yielded value is a chunk of bytes from the response body — typically
     * one SSE frame per chunk, though splits and joins are possible.
     *
     * @return \Generator<int, string>
     *
     * @throws ApiException        on non-2xx responses
     * @throws ConnectionException on transport-level failure
     * @throws TimeoutException    on connect or total timeout
     */
    public function stream(RequestInterface $request): \Generator
    {
        $request = $this->applyAuth($request);

        $ch = curl_init();
        if ($ch === false) {
            throw new ConnectionException('Failed to initialize cURL handle.', $request);
        }

        /** @var list<string> $headers */
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = $name . ': ' . $value;
            }
        }

        // The body chunks captured by the WRITEFUNCTION callback.
        // We intentionally use a string buffer instead of yielding from the
        // callback so a single curl_exec() drives the request — PHP's
        // generator-from-callback story is fragile.
        $bodyChunks = [];
        $statusCode = 0;
        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => (string) $request->getBody(),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->connectTimeout),
            CURLOPT_TIMEOUT => (int) ceil($this->totalTimeout),
            CURLOPT_HEADERFUNCTION => static function ($_ch, string $headerLine) use (&$responseHeaders, &$statusCode): int {
                $trimmed = trim($headerLine);
                if ($trimmed === '') {
                    return strlen($headerLine);
                }
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $trimmed, $m) === 1) {
                    $statusCode = (int) $m[1];
                    $responseHeaders = [];

                    return strlen($headerLine);
                }
                $colon = strpos($trimmed, ':');
                if ($colon !== false) {
                    $name = strtolower(trim(substr($trimmed, 0, $colon)));
                    $value = trim(substr($trimmed, $colon + 1));
                    $responseHeaders[$name][] = $value;
                }

                return strlen($headerLine);
            },
            CURLOPT_WRITEFUNCTION => static function ($_ch, string $data) use (&$bodyChunks): int {
                $bodyChunks[] = $data;

                return strlen($data);
            },
        ]);

        // We deliberately don't call curl_exec() and yield from a callback —
        // we exec once, then yield from the captured buffer. This means we
        // buffer the whole response. For SSE that's fine in practice for
        // small/medium responses; for unbounded streams a true async
        // transport (ReactPHP/Amp) would be needed.
        //
        // To get true streaming we use a different strategy: drive curl
        // multi-handle in non-blocking mode with curl_multi_select, yielding
        // chunks as they arrive.
        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $ch);

        try {
            $running = 0;
            do {
                $status = curl_multi_exec($multi, $running);
                if ($status > CURLM_OK) {
                    break;
                }

                while ($bodyChunks !== []) {
                    $chunk = array_shift($bodyChunks);

                    // First chunk: validate status code before streaming.
                    if ($statusCode >= 400) {
                        $responseBody = $chunk . implode('', $bodyChunks);
                        $bodyChunks = [];
                        throw $this->buildApiException($statusCode, $responseHeaders, $responseBody, $request);
                    }

                    yield $chunk;
                }

                if ($running > 0) {
                    curl_multi_select($multi, 1.0);
                }
            } while ($running > 0);

            // Drain anything that arrived after the last loop pass.
            while ($bodyChunks !== []) {
                $chunk = array_shift($bodyChunks);

                if ($statusCode >= 400) {
                    $responseBody = $chunk . implode('', $bodyChunks);
                    $bodyChunks = [];
                    throw $this->buildApiException($statusCode, $responseHeaders, $responseBody, $request);
                }

                yield $chunk;
            }

            $errno = curl_errno($ch);
            if ($errno !== 0) {
                $message = curl_error($ch);
                if ($errno === CURLE_OPERATION_TIMEOUTED) {
                    throw new TimeoutException($message, $request);
                }
                throw new ConnectionException($message, $request);
            }

            if ($statusCode === 0) {
                throw new ConnectionException('No HTTP response received from upstream.', $request);
            }

            if ($statusCode >= 400) {
                throw $this->buildApiException($statusCode, $responseHeaders, '', $request);
            }
        } finally {
            curl_multi_remove_handle($multi, $ch);
            curl_multi_close($multi);
            curl_close($ch);
        }
    }

    private function applyAuth(RequestInterface $request): RequestInterface
    {
        foreach ($this->auth->authenticate($request) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($this->userAgent !== null && !$request->hasHeader(Headers::USER_AGENT)) {
            $request = $request->withHeader(Headers::USER_AGENT, $this->userAgent);
        }

        return $request;
    }

    /**
     * @param  array<string, list<string>>  $headers
     */
    private function buildApiException(
        int $status,
        array $headers,
        string $body,
        RequestInterface $request,
    ): ApiException {
        // Reconstruct a minimal PSR-7 response just so ExceptionFactory can read it.
        $response = new MinimalResponse($status, $headers, $body);

        return ExceptionFactory::fromResponse($response, $request);
    }
}
