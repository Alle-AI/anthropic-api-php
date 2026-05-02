<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Auth;

use AlleAI\Anthropic\Auth\BedrockAuth;
use Aws\Credentials\Credentials;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BedrockAuth::class)]
final class BedrockAuthTest extends TestCase
{
    public function testApplyRewritesUrlAndTransformsBody(): void
    {
        $auth = new BedrockAuth(
            credentials: new Credentials('AKIATEST', 'secret-test'),
            region: 'us-east-1',
        );

        $factory = new Psr17Factory();
        $body = json_encode([
            'model' => 'anthropic.claude-sonnet-4-7-v1:0',
            'max_tokens' => 256,
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], JSON_THROW_ON_ERROR);

        $request = $factory->createRequest('POST', 'https://api.anthropic.com/v1/messages')
            ->withHeader('x-api-key', 'sk-test')
            ->withHeader('anthropic-version', '2023-06-01')
            ->withHeader('anthropic-idempotency-key', 'should-be-stripped')
            ->withHeader('content-type', 'application/json')
            ->withBody($factory->createStream($body));

        $signed = $auth->apply($request);

        // URL is rewritten to the Bedrock runtime endpoint with the model in the path.
        self::assertSame(
            'https://bedrock-runtime.us-east-1.amazonaws.com/model/anthropic.claude-sonnet-4-7-v1%3A0/invoke',
            (string) $signed->getUri(),
        );

        // Anthropic-only headers stripped.
        self::assertFalse($signed->hasHeader('x-api-key'));
        self::assertFalse($signed->hasHeader('anthropic-version'));
        self::assertFalse($signed->hasHeader('anthropic-idempotency-key'));

        // SigV4 sets these.
        self::assertNotSame('', $signed->getHeaderLine('Authorization'));
        self::assertStringStartsWith('AWS4-HMAC-SHA256', $signed->getHeaderLine('Authorization'));
        self::assertNotSame('', $signed->getHeaderLine('X-Amz-Date'));

        // Body has model removed and anthropic_version added.
        $signedBody = json_decode((string) $signed->getBody(), true);
        self::assertIsArray($signedBody);
        self::assertArrayNotHasKey('model', $signedBody);
        self::assertSame(BedrockAuth::BEDROCK_ANTHROPIC_VERSION, $signedBody['anthropic_version']);
        self::assertSame(256, $signedBody['max_tokens']);
    }

    public function testApplyRoutesStreamingRequestsToInvokeWithResponseStream(): void
    {
        $auth = new BedrockAuth(
            credentials: new Credentials('AKIATEST', 'secret-test'),
            region: 'us-west-2',
        );
        $factory = new Psr17Factory();
        $body = json_encode([
            'model' => 'anthropic.claude-haiku-4-5-v1:0',
            'max_tokens' => 64,
            'stream' => true,
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ], JSON_THROW_ON_ERROR);

        $request = $factory->createRequest('POST', 'https://api.anthropic.com/v1/messages')
            ->withBody($factory->createStream($body));

        $signed = $auth->apply($request);
        self::assertStringContainsString('/invoke-with-response-stream', (string) $signed->getUri());

        $signedBody = json_decode((string) $signed->getBody(), true);
        self::assertIsArray($signedBody);
        self::assertArrayNotHasKey('stream', $signedBody, 'stream flag is encoded in the URL, not the body');
    }

    public function testApplyRejectsRequestWithoutModel(): void
    {
        $auth = new BedrockAuth(
            credentials: new Credentials('AKIATEST', 'secret'),
            region: 'us-east-1',
        );
        $factory = new Psr17Factory();
        $request = $factory->createRequest('POST', 'https://api.anthropic.com/v1/messages')
            ->withBody($factory->createStream(json_encode(['max_tokens' => 1, 'messages' => []], JSON_THROW_ON_ERROR)));

        $this->expectException(\InvalidArgumentException::class);
        $auth->apply($request);
    }

    public function testBaseUrlAlwaysReturnsBedrockEndpoint(): void
    {
        $auth = new BedrockAuth(new Credentials('A', 'B'), 'eu-central-1');
        self::assertSame('https://bedrock-runtime.eu-central-1.amazonaws.com', $auth->baseUrl(null));
        self::assertSame('https://bedrock-runtime.eu-central-1.amazonaws.com', $auth->baseUrl('https://ignored/'));
    }
}
