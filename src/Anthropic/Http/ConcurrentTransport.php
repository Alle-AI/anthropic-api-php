<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

use AlleAI\Anthropic\Auth\AuthProvider;
use AlleAI\Anthropic\Auth\RequestTransformingAuthProvider;
use AlleAI\Anthropic\Exceptions\ConnectionException;
use AlleAI\Anthropic\Exceptions\ExceptionFactory;
use AlleAI\Anthropic\Exceptions\TimeoutException;
use Psr\Http\Message\RequestInterface;
use Ramsey\Uuid\Uuid;

/**
 * Sends N PSR-7 requests in parallel via libcurl's multi-handle, with a
 * configurable concurrency window. Each request is auth'd via the same
 * AuthProvider used by the rest of the SDK; per-request errors are
 * surfaced as the corresponding exception class without aborting the
 * whole batch.
 *
 * Used by {@see \AlleAI\Anthropic\Resources\Messages::createMany()} for
 * concurrent fan-out. Not part of the Transport interface — this is a
 * specialised channel that bypasses the standard middleware stack
 * (auth and idempotency are applied here directly; retries are not).
 */
final class ConcurrentTransport implements ConcurrentSender
{
    public function __construct(
        private readonly AuthProvider $auth,
        private readonly string $userAgent,
        private readonly float $totalTimeout = 600.0,
        private readonly float $connectTimeout = 30.0,
    ) {
    }

    /**
     * Send all `$requests` in parallel, honoring `$concurrency` as the max
     * number in flight at any moment.
     *
     * @param  list<RequestInterface>  $requests
     *
     * @return list<ConcurrentResult>  one entry per input request, in order
     */
    public function sendAll(array $requests, int $concurrency = 5): array
    {
        if ($requests === []) {
            return [];
        }
        if ($concurrency < 1) {
            throw new \InvalidArgumentException('Concurrency must be at least 1.');
        }

        $prepared = array_map(fn (RequestInterface $r): RequestInterface => $this->prepare($r), $requests);
        $count = count($prepared);

        /** @var list<?ConcurrentResult> $results */
        $results = array_fill(0, $count, null);
        $multi = curl_multi_init();

        /** @var array<int, array{ch: \CurlHandle, request: RequestInterface, ctx: HandleContext}> $handles */
        $handles = [];

        try {
            $next = 0;
            $launch = function (int $index) use (&$handles, $prepared, $multi): void {
                $request = $prepared[$index];
                $ctx = new HandleContext();
                $ch = $this->newHandle($request, $ctx);
                $handles[$index] = ['ch' => $ch, 'request' => $request, 'ctx' => $ctx];
                curl_multi_add_handle($multi, $ch);
            };

            while ($next < $count && $next < $concurrency) {
                $launch($next++);
            }

            $running = 0;
            do {
                $status = curl_multi_exec($multi, $running);
                if ($status > CURLM_OK) {
                    break;
                }

                while ($info = curl_multi_info_read($multi)) {
                    $finished = $info['handle'];
                    if (!$finished instanceof \CurlHandle) {
                        continue;
                    }
                    $finishedIndex = null;
                    foreach ($handles as $idx => $entry) {
                        if ($entry['ch'] === $finished) {
                            $finishedIndex = $idx;
                            break;
                        }
                    }
                    if ($finishedIndex === null) {
                        continue;
                    }

                    $entry = $handles[$finishedIndex];
                    $results[$finishedIndex] = $this->collectResult($entry['ch'], $entry['request'], $entry['ctx']);

                    curl_multi_remove_handle($multi, $finished);
                    curl_close($finished);
                    unset($handles[$finishedIndex]);

                    if ($next < $count) {
                        $launch($next++);
                    }
                }

                if ($running > 0 || $next < $count) {
                    curl_multi_select($multi, 1.0);
                }
            } while ($running > 0 || $next < $count);
        } finally {
            foreach ($handles as $entry) {
                curl_multi_remove_handle($multi, $entry['ch']);
                curl_close($entry['ch']);
            }
            curl_multi_close($multi);
        }

        $finalised = [];
        foreach ($results as $r) {
            $finalised[] = $r ?? new ConcurrentResult(
                null,
                null,
                new ConnectionException('Concurrent transport did not produce a result for this request.'),
            );
        }

        return $finalised;
    }

    private function prepare(RequestInterface $request): RequestInterface
    {
        if ($this->auth instanceof RequestTransformingAuthProvider) {
            $request = $this->auth->apply($request);
        } else {
            foreach ($this->auth->authenticate($request) as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        if (!$request->hasHeader(Headers::USER_AGENT)) {
            $request = $request->withHeader(Headers::USER_AGENT, $this->userAgent);
        }
        if ($request->getMethod() === 'POST' && !$request->hasHeader(Headers::IDEMPOTENCY_KEY)) {
            $request = $request->withHeader(Headers::IDEMPOTENCY_KEY, Uuid::uuid7()->toString());
        }

        return $request;
    }

    private function newHandle(RequestInterface $request, HandleContext $ctx): \CurlHandle
    {
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

        curl_setopt_array($ch, [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => (string) $request->getBody(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => (int) ceil($this->connectTimeout),
            CURLOPT_TIMEOUT => (int) ceil($this->totalTimeout),
            CURLOPT_HEADERFUNCTION => static function ($_ch, string $line) use ($ctx): int {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    return strlen($line);
                }
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $trimmed, $m) === 1) {
                    $ctx->statusCode = (int) $m[1];
                    $ctx->headers = [];

                    return strlen($line);
                }
                $colon = strpos($trimmed, ':');
                if ($colon !== false) {
                    $name = strtolower(trim(substr($trimmed, 0, $colon)));
                    $value = trim(substr($trimmed, $colon + 1));
                    $ctx->headers[$name][] = $value;
                }

                return strlen($line);
            },
        ]);

        return $ch;
    }

    private function collectResult(\CurlHandle $ch, RequestInterface $request, HandleContext $ctx): ConcurrentResult
    {
        $body = curl_multi_getcontent($ch);
        $errno = curl_errno($ch);

        if ($errno !== 0) {
            $message = curl_error($ch);
            $exception = $errno === CURLE_OPERATION_TIMEOUTED
                ? new TimeoutException($message, $request)
                : new ConnectionException($message, $request);

            return new ConcurrentResult(null, null, $exception);
        }

        $bodyText = is_string($body) ? $body : '';

        if ($ctx->statusCode >= 400) {
            $response = new MinimalResponse($ctx->statusCode, $ctx->headers, $bodyText);
            $exception = ExceptionFactory::fromResponse($response, $request);

            return new ConcurrentResult($ctx->statusCode, $bodyText, $exception);
        }

        return new ConcurrentResult($ctx->statusCode, $bodyText, null);
    }
}
