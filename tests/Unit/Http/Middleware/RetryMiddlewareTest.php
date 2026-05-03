<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Http\Middleware;

use AlleAI\Anthropic\Exceptions\ConnectionException;
use AlleAI\Anthropic\Http\Middleware\RetryMiddleware;
use AlleAI\Anthropic\Http\RetryPolicy;
use AlleAI\Anthropic\Tests\Support\RecordingSleeper;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(RetryMiddleware::class)]
final class RetryMiddlewareTest extends TestCase
{
    public function testReturnsResponseImmediatelyOnSuccess(): void
    {
        $factory = new Psr17Factory();
        $sleeper = new RecordingSleeper();
        $middleware = new RetryMiddleware(new RetryPolicy(maxAttempts: 3, jitter: 0.0), $sleeper);

        $calls = 0;
        $response = $middleware->handle(
            $factory->createRequest('POST', 'https://example/'),
            static function (RequestInterface $request) use ($factory, &$calls): ResponseInterface {
                $calls++;

                return $factory->createResponse(200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $calls);
        self::assertSame([], $sleeper->durations);
    }

    public function testRetriesRetryableStatusUntilSuccess(): void
    {
        $factory = new Psr17Factory();
        $sleeper = new RecordingSleeper();
        $middleware = new RetryMiddleware(
            new RetryPolicy(maxAttempts: 4, baseDelay: 0.01, jitter: 0.0),
            $sleeper,
        );

        $calls = 0;
        $response = $middleware->handle(
            $factory->createRequest('POST', 'https://example/'),
            static function (RequestInterface $request) use ($factory, &$calls): ResponseInterface {
                $calls++;

                return $factory->createResponse($calls < 3 ? 529 : 200);
            },
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(3, $calls);
        self::assertCount(2, $sleeper->durations);
    }

    public function testGivesUpAfterMaxAttempts(): void
    {
        $factory = new Psr17Factory();
        $sleeper = new RecordingSleeper();
        $middleware = new RetryMiddleware(
            new RetryPolicy(maxAttempts: 2, baseDelay: 0.0, jitter: 0.0),
            $sleeper,
        );

        $calls = 0;
        $response = $middleware->handle(
            $factory->createRequest('POST', 'https://example/'),
            static function (RequestInterface $request) use ($factory, &$calls): ResponseInterface {
                $calls++;

                return $factory->createResponse(529);
            },
        );

        self::assertSame(529, $response->getStatusCode());
        self::assertSame(2, $calls);
    }

    public function testHonorsRetryAfterHeader(): void
    {
        $factory = new Psr17Factory();
        $sleeper = new RecordingSleeper();
        $middleware = new RetryMiddleware(
            new RetryPolicy(maxAttempts: 3, baseDelay: 100.0, jitter: 0.0),
            $sleeper,
        );

        $calls = 0;
        $middleware->handle(
            $factory->createRequest('POST', 'https://example/'),
            static function (RequestInterface $request) use ($factory, &$calls): ResponseInterface {
                $calls++;
                if ($calls === 1) {
                    return $factory->createResponse(429)->withHeader('Retry-After', '7');
                }

                return $factory->createResponse(200);
            },
        );

        self::assertCount(1, $sleeper->durations);
        self::assertEqualsWithDelta(7.0, $sleeper->durations[0], 0.0001);
    }

    public function testReraisesConnectionExceptionAfterExhaustingAttempts(): void
    {
        $factory = new Psr17Factory();
        $sleeper = new RecordingSleeper();
        $middleware = new RetryMiddleware(
            new RetryPolicy(maxAttempts: 2, baseDelay: 0.0, jitter: 0.0),
            $sleeper,
        );

        $this->expectException(ConnectionException::class);

        $middleware->handle(
            $factory->createRequest('POST', 'https://example/'),
            static function (RequestInterface $request): ResponseInterface {
                throw new ConnectionException('boom', $request);
            },
        );
    }
}
