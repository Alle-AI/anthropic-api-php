<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Auth;

use AlleAI\Anthropic\Auth\VertexAuth;
use Google\Auth\FetchAuthTokenInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VertexAuth::class)]
final class VertexAuthTest extends TestCase
{
    public function testApplyAttachesBearerAndRewritesUrl(): void
    {
        $auth = new VertexAuth(
            tokenFetcher: new StubTokenFetcher('test-access-token'),
            projectId: 'my-project',
            region: 'us-east5',
        );

        $factory = new Psr17Factory();
        $body = json_encode([
            'model' => 'claude-sonnet-4-7@20260101',
            'max_tokens' => 256,
            'messages' => [['role' => 'user', 'content' => 'Hello']],
        ], JSON_THROW_ON_ERROR);

        $request = $factory->createRequest('POST', 'https://api.anthropic.com/v1/messages')
            ->withHeader('x-api-key', 'sk-strip-me')
            ->withHeader('anthropic-version', '2023-06-01')
            ->withBody($factory->createStream($body));

        $applied = $auth->apply($request);

        self::assertSame(
            'https://us-east5-aiplatform.googleapis.com/v1/projects/my-project/locations/us-east5/publishers/anthropic/models/claude-sonnet-4-7%4020260101:rawPredict',
            (string) $applied->getUri(),
        );
        self::assertSame('Bearer test-access-token', $applied->getHeaderLine('Authorization'));
        self::assertFalse($applied->hasHeader('x-api-key'));
        self::assertFalse($applied->hasHeader('anthropic-version'));

        $signedBody = json_decode((string) $applied->getBody(), true);
        self::assertIsArray($signedBody);
        self::assertArrayNotHasKey('model', $signedBody);
        self::assertSame(VertexAuth::VERTEX_ANTHROPIC_VERSION, $signedBody['anthropic_version']);
    }

    public function testApplyRoutesStreamingRequestsToStreamRawPredict(): void
    {
        $auth = new VertexAuth(
            tokenFetcher: new StubTokenFetcher('tok'),
            projectId: 'p',
            region: 'us-east5',
        );
        $factory = new Psr17Factory();
        $body = json_encode([
            'model' => 'claude-haiku-4-5',
            'max_tokens' => 32,
            'stream' => true,
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ], JSON_THROW_ON_ERROR);
        $request = $factory->createRequest('POST', 'https://api.anthropic.com/v1/messages')
            ->withBody($factory->createStream($body));

        $applied = $auth->apply($request);
        self::assertStringEndsWith(':streamRawPredict', (string) $applied->getUri());

        $signedBody = json_decode((string) $applied->getBody(), true);
        self::assertIsArray($signedBody);
        self::assertArrayNotHasKey('stream', $signedBody);
    }

    public function testGlobalRegionUsesUnprefixedHost(): void
    {
        $auth = new VertexAuth(
            tokenFetcher: new StubTokenFetcher('tok'),
            projectId: 'p',
            region: 'global',
        );
        self::assertSame('https://aiplatform.googleapis.com', $auth->baseUrl(null));
    }

    public function testTokenFetcherFailureSurfacesAsRuntimeException(): void
    {
        $auth = new VertexAuth(
            tokenFetcher: new StubTokenFetcher(''),
            projectId: 'p',
            region: 'us-east5',
        );
        $factory = new Psr17Factory();
        $body = json_encode([
            'model' => 'claude-sonnet-4-7',
            'max_tokens' => 1,
            'messages' => [['role' => 'user', 'content' => 'x']],
        ], JSON_THROW_ON_ERROR);
        $request = $factory->createRequest('POST', 'https://api.anthropic.com/v1/messages')
            ->withBody($factory->createStream($body));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('access_token');
        $auth->apply($request);
    }
}

final class StubTokenFetcher implements FetchAuthTokenInterface
{
    public function __construct(private readonly string $token)
    {
    }

    public function fetchAuthToken(?callable $httpHandler = null): array
    {
        return $this->token === '' ? [] : ['access_token' => $this->token];
    }

    public function getCacheKey(): string
    {
        return 'stub';
    }

    public function getLastReceivedToken(): ?array
    {
        return $this->token === '' ? null : ['access_token' => $this->token];
    }
}
