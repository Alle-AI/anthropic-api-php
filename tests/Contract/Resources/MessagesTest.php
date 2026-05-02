<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Contract\Resources;

use AlleAI\Anthropic\Exceptions\AuthenticationException;
use AlleAI\Anthropic\Exceptions\OverloadedException;
use AlleAI\Anthropic\Exceptions\RateLimitException;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Messages\Content\CacheControl;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Resources\Messages;
use AlleAI\Anthropic\Tests\Support\Fixture;
use AlleAI\Anthropic\Tests\Support\TestClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Messages::class)]
final class MessagesTest extends TestCase
{
    public function testCreateSendsExpectedPayloadAndParsesResponse(): void
    {
        [$client, $http] = TestClientFactory::make(apiKey: 'sk-test');
        $http->pushJsonResponse(200, Fixture::json('messages/simple.json'));

        $response = $client->messages()->create(
            model: Model::CLAUDE_SONNET_4_7,
            maxTokens: 256,
            messages: [['role' => 'user', 'content' => 'Hi']],
        );

        self::assertSame('Hello! How can I help you today?', $response->text());
        self::assertSame(StopReason::END_TURN, $response->stopReason);

        $body = $http->lastRequestBody();
        self::assertSame('claude-sonnet-4-7', $body['model']);
        self::assertSame(256, $body['max_tokens']);
        self::assertSame([['role' => 'user', 'content' => 'Hi']], $body['messages']);
        self::assertArrayNotHasKey('stream', $body);
    }

    public function testCreateAttachesRequiredHeaders(): void
    {
        [$client, $http] = TestClientFactory::make(apiKey: 'sk-test');
        $http->pushJsonResponse(200, Fixture::json('messages/simple.json'));

        $client->messages()->create(
            model: Model::CLAUDE_SONNET_4_7,
            maxTokens: 32,
            messages: [['role' => 'user', 'content' => 'Hi']],
        );

        $req = $http->lastRequest();
        self::assertSame('sk-test', $req->getHeaderLine(Headers::X_API_KEY));
        self::assertSame(Headers::DEFAULT_API_VERSION, $req->getHeaderLine(Headers::ANTHROPIC_VERSION));
        self::assertSame('application/json', $req->getHeaderLine(Headers::CONTENT_TYPE));
        self::assertNotSame('', $req->getHeaderLine(Headers::IDEMPOTENCY_KEY));
        self::assertNotSame('', $req->getHeaderLine(Headers::USER_AGENT));
    }

    public function testBetaHeadersAreCommaJoined(): void
    {
        [$client, $http] = TestClientFactory::make(beta: ['feature-a-2025-01-01', 'feature-b-2025-02-01']);
        $http->pushJsonResponse(200, Fixture::json('messages/simple.json'));

        $client->messages()->create(
            model: Model::CLAUDE_SONNET_4_7,
            maxTokens: 32,
            messages: [['role' => 'user', 'content' => 'Hi']],
        );

        self::assertSame(
            'feature-a-2025-01-01,feature-b-2025-02-01',
            $http->lastRequest()->getHeaderLine(Headers::ANTHROPIC_BETA),
        );
    }

    public function testNormalizesContentBlocksToWireFormat(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('messages/simple.json'));

        $client->messages()->create(
            model: Model::CLAUDE_SONNET_4_7,
            maxTokens: 32,
            messages: [[
                'role' => 'user',
                'content' => [
                    TextBlock::of('hello'),
                    'world',
                ],
            ]],
        );

        $body = $http->lastRequestBody();
        self::assertSame([
            ['type' => 'text', 'text' => 'hello'],
            ['type' => 'text', 'text' => 'world'],
        ], $body['messages'][0]['content']);
    }

    public function testSystemPromptCanBeBlocksWithCacheControl(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('messages/simple.json'));

        $client->messages()->create(
            model: Model::CLAUDE_SONNET_4_7,
            maxTokens: 32,
            messages: [['role' => 'user', 'content' => 'Hi']],
            system: [
                TextBlock::of('You are helpful.'),
                TextBlock::of('Long context')->withCacheControl(CacheControl::ephemeral('1h')),
            ],
        );

        $body = $http->lastRequestBody();
        self::assertIsArray($body['system']);
        self::assertSame('ephemeral', $body['system'][1]['cache_control']['type']);
        self::assertSame('1h', $body['system'][1]['cache_control']['ttl']);
    }

    public function test401ThrowsAuthenticationException(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(401, Fixture::json('errors/401_authentication.json'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('invalid x-api-key');

        $client->messages()->create(
            model: Model::CLAUDE_SONNET_4_7,
            maxTokens: 32,
            messages: [['role' => 'user', 'content' => 'Hi']],
        );
    }

    public function test429ThrowsRateLimitException(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(429, Fixture::json('errors/429_rate_limit.json'), ['Retry-After' => '11']);

        try {
            $client->messages()->create(
                model: Model::CLAUDE_SONNET_4_7,
                maxTokens: 32,
                messages: [['role' => 'user', 'content' => 'Hi']],
            );
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(11, $e->retryAfter());
            self::assertSame('rate_limit_error', $e->errorType);
        }
    }

    public function test529ThrowsOverloadedException(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(529, Fixture::json('errors/529_overloaded.json'));

        $this->expectException(OverloadedException::class);

        $client->messages()->create(
            model: Model::CLAUDE_SONNET_4_7,
            maxTokens: 32,
            messages: [['role' => 'user', 'content' => 'Hi']],
        );
    }
}
