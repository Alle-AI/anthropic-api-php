<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Resources;

use AlleAI\Anthropic\Batches\BatchEntry;
use AlleAI\Anthropic\Batches\BatchResponse;
use AlleAI\Anthropic\Batches\BatchResult;
use AlleAI\Anthropic\Beta\BetaHeaders;
use AlleAI\Anthropic\ClientOptions;
use AlleAI\Anthropic\Exceptions\AnthropicException;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\Transport;
use AlleAI\Anthropic\Util\Json;
use AlleAI\Anthropic\Util\Sleeper;
use AlleAI\Anthropic\Util\SystemSleeper;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Resource for the Message Batches API (`/v1/messages/batches`).
 *
 * Batches let you submit up to 100,000 Messages requests in a single call;
 * Anthropic processes them asynchronously at a 50% discount and returns a
 * JSONL results stream. Typical flow:
 *
 * ```php
 * $batch = $client->batches()->create([
 *     new BatchEntry('row-1', ['model' => Model::CLAUDE_HAIKU_4_5, 'max_tokens' => 256, 'messages' => [...]]),
 *     new BatchEntry('row-2', [...]),
 * ]);
 *
 * $done = $client->batches()->pollUntilDone($batch->id);
 *
 * foreach ($client->batches()->results($done->id) as $result) {
 *     echo $result->customId, ' -> ', $result->message?->text(), "\n";
 * }
 * ```
 */
final class Batches
{
    public function __construct(
        private readonly Transport $transport,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ClientOptions $options,
        private readonly Sleeper $sleeper = new SystemSleeper(),
    ) {
    }

    /**
     * @param  list<BatchEntry>  $requests
     */
    public function create(array $requests): BatchResponse
    {
        if ($requests === []) {
            throw new \InvalidArgumentException('Batch must contain at least one request.');
        }

        $body = [
            'requests' => array_map(static fn (BatchEntry $e): array => $e->toArray(), $requests),
        ];

        $request = $this->requestFactory->createRequest('POST', $this->endpoint('/v1/messages/batches'));
        $request = $this->withDefaultHeaders($request)
            ->withBody($this->streamFactory->createStream(Json::encode($body)));

        $response = $this->transport->sendRequest($request);

        return BatchResponse::fromArray(Json::decode((string) $response->getBody()));
    }

    public function get(string $batchId): BatchResponse
    {
        $request = $this->withDefaultHeaders(
            $this->requestFactory->createRequest('GET', $this->endpoint('/v1/messages/batches/' . rawurlencode($batchId))),
        );
        $response = $this->transport->sendRequest($request);

        return BatchResponse::fromArray(Json::decode((string) $response->getBody()));
    }

    public function cancel(string $batchId): BatchResponse
    {
        $request = $this->withDefaultHeaders(
            $this->requestFactory->createRequest('POST', $this->endpoint('/v1/messages/batches/' . rawurlencode($batchId) . '/cancel')),
        );
        $response = $this->transport->sendRequest($request);

        return BatchResponse::fromArray(Json::decode((string) $response->getBody()));
    }

    /**
     * Poll the batch until it reaches a terminal state or `$timeoutSeconds`
     * elapses. Returns the latest BatchResponse on success, or throws on
     * timeout.
     */
    public function pollUntilDone(
        string $batchId,
        float $intervalSeconds = 30.0,
        float $timeoutSeconds = 86_400.0,
    ): BatchResponse {
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $response = $this->get($batchId);
            if ($response->isComplete()) {
                return $response;
            }

            if (microtime(true) >= $deadline) {
                throw new AnthropicException(sprintf(
                    'Timed out waiting for batch %s to finish (status=%s).',
                    $batchId,
                    $response->processingStatus->value,
                ));
            }

            $this->sleeper->sleep($intervalSeconds);
        }
    }

    /**
     * Stream results line-by-line from the batch's JSONL file.
     *
     * @return \Generator<int, BatchResult>
     */
    public function results(string $batchId): \Generator
    {
        $batch = $this->get($batchId);
        if (!$batch->isComplete()) {
            throw new AnthropicException(sprintf(
                'Batch %s has not finished yet (status=%s); call pollUntilDone() first.',
                $batchId,
                $batch->processingStatus->value,
            ));
        }

        $request = $this->withDefaultHeaders(
            $this->requestFactory->createRequest('GET', $this->endpoint('/v1/messages/batches/' . rawurlencode($batchId) . '/results')),
        );
        $response = $this->transport->sendRequest($request);
        $body = $response->getBody();
        $body->rewind();

        $buffer = '';
        while (!$body->eof()) {
            $buffer .= $body->read(8192);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                /** @var array<array-key, mixed> $decoded */
                $decoded = Json::decode($line);
                yield BatchResult::fromArray($decoded);
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            /** @var array<array-key, mixed> $decoded */
            $decoded = Json::decode($tail);
            yield BatchResult::fromArray($decoded);
        }
    }

    /**
     * @return list<BatchResponse>
     */
    public function list(?string $beforeId = null, ?string $afterId = null, ?int $limit = null): array
    {
        $query = [];
        if ($beforeId !== null) {
            $query['before_id'] = $beforeId;
        }
        if ($afterId !== null) {
            $query['after_id'] = $afterId;
        }
        if ($limit !== null) {
            $query['limit'] = (string) $limit;
        }

        $url = $this->endpoint('/v1/messages/batches');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $request = $this->withDefaultHeaders($this->requestFactory->createRequest('GET', $url));
        $response = $this->transport->sendRequest($request);
        $decoded = Json::decode((string) $response->getBody());

        $out = [];
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            foreach ($decoded['data'] as $entry) {
                if (is_array($entry)) {
                    /** @var array<array-key, mixed> $entry */
                    $out[] = BatchResponse::fromArray($entry);
                }
            }
        }

        return $out;
    }

    private function withDefaultHeaders(RequestInterface $request): RequestInterface
    {
        $request = $request
            ->withHeader(Headers::ACCEPT, 'application/json')
            ->withHeader(Headers::CONTENT_TYPE, 'application/json')
            ->withHeader(Headers::ANTHROPIC_VERSION, $this->options->anthropicVersion);

        $beta = $this->options->anthropicBeta;
        if (!in_array(BetaHeaders::MESSAGE_BATCHES, $beta, true)) {
            $beta[] = BetaHeaders::MESSAGE_BATCHES;
        }
        $request = $request->withHeader(Headers::ANTHROPIC_BETA, implode(',', $beta));

        return $request;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->options->baseUrl, '/') . $path;
    }
}
