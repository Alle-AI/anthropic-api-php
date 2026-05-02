<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Contract\Resources;

use AlleAI\Anthropic\Exceptions\AuthenticationException;
use AlleAI\Anthropic\Exceptions\TimeoutException;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Messages\MessageResponse;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Resources\Messages;
use AlleAI\Anthropic\Tests\Support\FakeConcurrentSender;
use AlleAI\Anthropic\Tests\Support\Fixture;
use AlleAI\Anthropic\Tests\Support\TestClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Messages::class)]
final class MessagesCreateManyTest extends TestCase
{
    public function testEmptyInputReturnsEmpty(): void
    {
        $sender = new FakeConcurrentSender();
        [$client] = TestClientFactory::make(concurrentSender: $sender);
        self::assertSame([], $client->messages()->createMany([]));
        self::assertSame([], $sender->captured);
    }

    public function testHappyPathParsesEachResponse(): void
    {
        $sender = new FakeConcurrentSender();
        $sender->pushSuccessJson(200, Fixture::json('messages/simple.json'));
        $sender->pushSuccessJson(200, Fixture::json('messages/tool_use.json'));

        [$client] = TestClientFactory::make(concurrentSender: $sender);

        $results = $client->messages()->createMany([
            ['model' => Model::CLAUDE_SONNET_4_7, 'maxTokens' => 256, 'messages' => [['role' => 'user', 'content' => 'Hi']]],
            ['model' => Model::CLAUDE_SONNET_4_7, 'maxTokens' => 256, 'messages' => [['role' => 'user', 'content' => 'Tools?']]],
        ], concurrency: 4);

        self::assertCount(2, $results);
        self::assertInstanceOf(MessageResponse::class, $results[0]);
        self::assertInstanceOf(MessageResponse::class, $results[1]);
        self::assertSame('Hello! How can I help you today?', $results[0]->text());
        self::assertTrue($results[1]->hasToolUse());

        self::assertSame(4, $sender->lastConcurrency);
        self::assertCount(2, $sender->captured);
    }

    public function testCapturedRequestsHaveAuthAndVersionHeaders(): void
    {
        $sender = new FakeConcurrentSender();
        $sender->pushSuccessJson(200, Fixture::json('messages/simple.json'));
        [$client] = TestClientFactory::make(apiKey: 'sk-test', concurrentSender: $sender);

        $client->messages()->createMany([
            ['model' => Model::CLAUDE_SONNET_4_7, 'maxTokens' => 32, 'messages' => [['role' => 'user', 'content' => 'Hi']]],
        ]);

        self::assertCount(1, $sender->captured);
        $req = $sender->captured[0];
        // Auth is applied INSIDE the sender; the captured request is what was passed in.
        // We assert the SDK-set headers that travel through anyway:
        self::assertSame('application/json', $req->getHeaderLine(Headers::CONTENT_TYPE));
        self::assertSame(Headers::DEFAULT_API_VERSION, $req->getHeaderLine(Headers::ANTHROPIC_VERSION));
        $body = json_decode((string) $req->getBody(), true);
        self::assertIsArray($body);
        self::assertSame('claude-sonnet-4-7', $body['model']);
    }

    public function testFailureSurfacesAsExceptionInResultList(): void
    {
        $sender = new FakeConcurrentSender();
        $sender->pushSuccessJson(200, Fixture::json('messages/simple.json'));
        $sender->pushError(new TimeoutException('upstream timed out'));

        [$client] = TestClientFactory::make(concurrentSender: $sender);

        $results = $client->messages()->createMany([
            ['model' => Model::CLAUDE_SONNET_4_7, 'maxTokens' => 32, 'messages' => [['role' => 'user', 'content' => 'A']]],
            ['model' => Model::CLAUDE_SONNET_4_7, 'maxTokens' => 32, 'messages' => [['role' => 'user', 'content' => 'B']]],
        ]);

        self::assertInstanceOf(MessageResponse::class, $results[0]);
        self::assertInstanceOf(TimeoutException::class, $results[1]);
    }

    public function testApiErrorSurfacesAsTypedException(): void
    {
        $sender = new FakeConcurrentSender();
        $sender->pushError(new AuthenticationException(
            message: 'invalid x-api-key',
            status: 401,
            errorType: 'authentication_error',
            requestId: null,
            headers: [],
            rawBody: '',
        ));
        [$client] = TestClientFactory::make(concurrentSender: $sender);

        $results = $client->messages()->createMany([
            ['model' => Model::CLAUDE_SONNET_4_7, 'maxTokens' => 32, 'messages' => [['role' => 'user', 'content' => 'A']]],
        ]);

        self::assertInstanceOf(AuthenticationException::class, $results[0]);
    }

    public function testInvalidEntryPlacesBuildErrorAndStillSendsValidOnes(): void
    {
        $sender = new FakeConcurrentSender();
        $sender->pushSuccessJson(200, Fixture::json('messages/simple.json'));

        [$client] = TestClientFactory::make(concurrentSender: $sender);

        $results = $client->messages()->createMany([
            ['model' => Model::CLAUDE_SONNET_4_7],  // missing maxTokens / messages
            ['model' => Model::CLAUDE_SONNET_4_7, 'maxTokens' => 32, 'messages' => [['role' => 'user', 'content' => 'B']]],
        ]);

        self::assertInstanceOf(\AlleAI\Anthropic\Exceptions\AnthropicException::class, $results[0]);
        self::assertInstanceOf(MessageResponse::class, $results[1]);
        self::assertCount(1, $sender->captured, 'only the valid entry should reach the sender');
    }
}
