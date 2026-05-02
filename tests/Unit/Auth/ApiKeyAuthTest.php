<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Auth;

use AlleAI\Anthropic\Auth\ApiKeyAuth;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiKeyAuth::class)]
final class ApiKeyAuthTest extends TestCase
{
    public function testAuthenticateReturnsApiKeyHeader(): void
    {
        $auth = new ApiKeyAuth('sk-ant-test');
        $request = (new Psr17Factory())->createRequest('POST', 'https://api.anthropic.com/v1/messages');

        self::assertSame(['x-api-key' => 'sk-ant-test'], $auth->authenticate($request));
    }

    public function testRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ApiKeyAuth('   ');
    }

    public function testFromEnvironmentLoadsExistingVariable(): void
    {
        putenv('TEST_API_KEY_ABC123=sk-ant-from-env');
        try {
            $auth = ApiKeyAuth::fromEnvironment('TEST_API_KEY_ABC123');
            $request = (new Psr17Factory())->createRequest('POST', 'https://api.anthropic.com/v1/messages');
            self::assertSame(['x-api-key' => 'sk-ant-from-env'], $auth->authenticate($request));
        } finally {
            putenv('TEST_API_KEY_ABC123');
        }
    }

    public function testFromEnvironmentThrowsWhenUnset(): void
    {
        putenv('TEST_API_KEY_MISSING_99999');
        $this->expectException(\RuntimeException::class);
        ApiKeyAuth::fromEnvironment('TEST_API_KEY_MISSING_99999');
    }

    public function testBaseUrlPassesThroughConfiguredValue(): void
    {
        $auth = new ApiKeyAuth('k');
        self::assertSame('https://example.test', $auth->baseUrl('https://example.test'));
        self::assertNull($auth->baseUrl(null));
    }
}
