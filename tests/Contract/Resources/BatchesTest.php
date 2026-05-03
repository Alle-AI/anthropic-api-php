<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Contract\Resources;

use AlleAI\Anthropic\Auth\ApiKeyAuth;
use AlleAI\Anthropic\Batches\BatchEntry;
use AlleAI\Anthropic\Batches\BatchStatus;
use AlleAI\Anthropic\Beta\BetaHeaders;
use AlleAI\Anthropic\ClientOptions;
use AlleAI\Anthropic\Exceptions\AnthropicException;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\Middleware\AuthMiddleware;
use AlleAI\Anthropic\Http\Middleware\IdempotencyMiddleware;
use AlleAI\Anthropic\Http\Middleware\RetryMiddleware;
use AlleAI\Anthropic\Http\Middleware\UserAgentMiddleware;
use AlleAI\Anthropic\Http\Psr18Transport;
use AlleAI\Anthropic\Http\RetryPolicy;
use AlleAI\Anthropic\Resources\Batches;
use AlleAI\Anthropic\Tests\Support\FakePsr18Client;
use AlleAI\Anthropic\Tests\Support\Fixture;
use AlleAI\Anthropic\Tests\Support\RecordingSleeper;
use AlleAI\Anthropic\Tests\Support\TestClientFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Batches::class)]
final class BatchesTest extends TestCase
{
    public function testCreatePostsRequestsArray(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('batches/batch_in_progress.json'));

        $batch = $client->batches()->create([
            new BatchEntry('row-1', ['model' => 'claude-haiku-4-5', 'max_tokens' => 32, 'messages' => [['role' => 'user', 'content' => 'A']]]),
            new BatchEntry('row-2', ['model' => 'claude-haiku-4-5', 'max_tokens' => 32, 'messages' => [['role' => 'user', 'content' => 'B']]]),
        ]);

        self::assertSame('msgbatch_01HGS', $batch->id);
        self::assertSame(BatchStatus::IN_PROGRESS, $batch->processingStatus);

        $req = $http->lastRequest();
        self::assertSame('POST', $req->getMethod());
        self::assertSame('https://api.anthropic.com/v1/messages/batches', (string) $req->getUri());

        $body = $http->lastRequestBody();
        self::assertIsArray($body['requests']);
        self::assertCount(2, $body['requests']);
        self::assertIsArray($body['requests'][0]);
        self::assertIsArray($body['requests'][1]);
        self::assertSame('row-1', $body['requests'][0]['custom_id']);
        self::assertSame('row-2', $body['requests'][1]['custom_id']);
    }

    public function testCreateAttachesBatchesBetaHeader(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('batches/batch_in_progress.json'));

        $client->batches()->create([
            new BatchEntry('row-1', ['model' => 'claude-haiku-4-5', 'max_tokens' => 1, 'messages' => []]),
        ]);

        self::assertStringContainsString(
            BetaHeaders::MESSAGE_BATCHES,
            $http->lastRequest()->getHeaderLine(Headers::ANTHROPIC_BETA),
        );
    }

    public function testCreateRejectsEmptyBatch(): void
    {
        [$client] = TestClientFactory::make();
        $this->expectException(\InvalidArgumentException::class);
        $client->batches()->create([]);
    }

    public function testGetReturnsBatchResponse(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('batches/batch_ended.json'));

        $batch = $client->batches()->get('msgbatch_01HGS');

        self::assertSame(BatchStatus::ENDED, $batch->processingStatus);
        self::assertTrue($batch->isComplete());
        self::assertSame('GET', $http->lastRequest()->getMethod());
        self::assertSame(
            'https://api.anthropic.com/v1/messages/batches/msgbatch_01HGS',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testCancelPostsToCancelEndpoint(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('batches/batch_in_progress.json'));

        $client->batches()->cancel('msgbatch_01HGS');

        $req = $http->lastRequest();
        self::assertSame('POST', $req->getMethod());
        self::assertSame(
            'https://api.anthropic.com/v1/messages/batches/msgbatch_01HGS/cancel',
            (string) $req->getUri(),
        );
    }

    public function testListWithCursorsAddsQuery(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, [
            'data' => [Fixture::json('batches/batch_ended.json')],
            'has_more' => false,
            'first_id' => 'msgbatch_01HGS',
            'last_id' => 'msgbatch_01HGS',
        ]);

        $list = $client->batches()->list(beforeId: 'msgbatch_X', limit: 10);

        self::assertCount(1, $list);
        $url = (string) $http->lastRequest()->getUri();
        self::assertStringContainsString('before_id=msgbatch_X', $url);
        self::assertStringContainsString('limit=10', $url);
    }

    public function testResultsRefusesIfBatchNotComplete(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('batches/batch_in_progress.json'));

        $this->expectException(AnthropicException::class);
        $this->expectExceptionMessage('has not finished');

        iterator_to_array($client->batches()->results('msgbatch_01HGS'));
    }

    public function testResultsStreamsJsonlEntries(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('batches/batch_ended.json'));
        $http->pushRawResponse(200, Fixture::raw('batches/results.jsonl'));

        $results = iterator_to_array($client->batches()->results('msgbatch_01HGS'));

        self::assertCount(2, $results);
        self::assertSame('row-1', $results[0]->customId);
        self::assertSame('row-2', $results[1]->customId);
        self::assertSame('Hola', $results[0]->message?->text());
        self::assertSame('Bonjour', $results[1]->message?->text());

        self::assertStringEndsWith('/v1/messages/batches/msgbatch_01HGS/results', (string) $http->lastRequest()->getUri());
    }

    public function testPollUntilDoneRetriesUntilTerminal(): void
    {
        $http = new FakePsr18Client();
        $http->pushJsonResponse(200, Fixture::json('batches/batch_in_progress.json'));
        $http->pushJsonResponse(200, Fixture::json('batches/batch_in_progress.json'));
        $http->pushJsonResponse(200, Fixture::json('batches/batch_ended.json'));

        $factory = new Psr17Factory();
        $options = new ClientOptions(
            auth: new ApiKeyAuth('test'),
            baseUrl: Headers::DEFAULT_BASE_URL,
            anthropicVersion: Headers::DEFAULT_API_VERSION,
            anthropicBeta: [],
            retryPolicy: RetryPolicy::disabled(),
            timeout: 5.0,
            userAgentSuffix: null,
        );

        $sleeper = new RecordingSleeper();
        $transport = new Psr18Transport($http, [
            new AuthMiddleware($options->auth),
            new UserAgentMiddleware(null),
            new IdempotencyMiddleware(),
            new RetryMiddleware($options->retryPolicy, $sleeper),
        ]);

        $batches = new Batches($transport, $factory, $factory, $options, $sleeper);

        $done = $batches->pollUntilDone('msgbatch_01HGS', intervalSeconds: 0.5, timeoutSeconds: 60.0);

        self::assertTrue($done->isComplete());
        self::assertCount(3, $http->requests);
        // Two intervals between three poll attempts.
        self::assertGreaterThanOrEqual(2, count(array_filter($sleeper->durations, static fn (float $d): bool => $d === 0.5)));
    }
}
