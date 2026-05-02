<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Auth;

use AlleAI\Anthropic\Auth\BearerAuth;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BearerAuth::class)]
final class BearerAuthTest extends TestCase
{
    public function testStaticTokenAddsAuthorizationHeader(): void
    {
        $auth = new BearerAuth('abc123');
        $request = (new Psr17Factory())->createRequest('POST', 'https://example/');

        self::assertSame(['Authorization' => 'Bearer abc123'], $auth->authenticate($request));
    }

    public function testCallableTokenIsInvokedPerRequest(): void
    {
        $calls = 0;
        $auth = new BearerAuth(static function () use (&$calls): string {
            $calls++;

            return 'token-' . $calls;
        });

        $request = (new Psr17Factory())->createRequest('POST', 'https://example/');

        self::assertSame(['Authorization' => 'Bearer token-1'], $auth->authenticate($request));
        self::assertSame(['Authorization' => 'Bearer token-2'], $auth->authenticate($request));
        self::assertSame(2, $calls);
    }

    public function testEmptyTokenThrows(): void
    {
        $auth = new BearerAuth(static fn (): string => '');
        $this->expectException(\RuntimeException::class);
        $auth->authenticate((new Psr17Factory())->createRequest('POST', 'https://example/'));
    }
}
