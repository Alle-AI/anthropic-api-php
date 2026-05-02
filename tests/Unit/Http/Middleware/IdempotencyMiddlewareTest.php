<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Http\Middleware;

use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\Middleware\IdempotencyMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(IdempotencyMiddleware::class)]
final class IdempotencyMiddlewareTest extends TestCase
{
    public function testAddsIdempotencyKeyOnPost(): void
    {
        $factory = new Psr17Factory();
        $middleware = new IdempotencyMiddleware();

        $captured = null;
        $middleware->handle(
            $factory->createRequest('POST', 'https://example/v1/messages'),
            static function (RequestInterface $request) use ($factory, &$captured): ResponseInterface {
                $captured = $request;

                return $factory->createResponse(200);
            },
        );

        self::assertNotNull($captured);
        $key = $captured->getHeaderLine(Headers::IDEMPOTENCY_KEY);
        self::assertNotSame('', $key);
        // UUIDv7 is 36 chars (8-4-4-4-12 with hyphens).
        self::assertSame(36, strlen($key));
    }

    public function testDoesNotOverrideExistingKey(): void
    {
        $factory = new Psr17Factory();
        $middleware = new IdempotencyMiddleware();
        $request = $factory->createRequest('POST', 'https://example/')->withHeader(Headers::IDEMPOTENCY_KEY, 'caller-set');

        $captured = null;
        $middleware->handle(
            $request,
            static function (RequestInterface $r) use ($factory, &$captured): ResponseInterface {
                $captured = $r;

                return $factory->createResponse(200);
            },
        );

        self::assertNotNull($captured);
        self::assertSame('caller-set', $captured->getHeaderLine(Headers::IDEMPOTENCY_KEY));
    }

    public function testDoesNotAddOnNonPostMethods(): void
    {
        $factory = new Psr17Factory();
        $middleware = new IdempotencyMiddleware();

        $captured = null;
        $middleware->handle(
            $factory->createRequest('GET', 'https://example/v1/models'),
            static function (RequestInterface $r) use ($factory, &$captured): ResponseInterface {
                $captured = $r;

                return $factory->createResponse(200);
            },
        );

        self::assertNotNull($captured);
        self::assertFalse($captured->hasHeader(Headers::IDEMPOTENCY_KEY));
    }
}
