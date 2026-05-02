<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Http\Middleware;

use AlleAI\Anthropic\Http\Middleware\LoggingMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;

#[CoversClass(LoggingMiddleware::class)]
final class LoggingMiddlewareTest extends TestCase
{
    public function testLogsRequestAndResponseWithCorrelationId(): void
    {
        $logger = new RecordingLogger();
        $middleware = new LoggingMiddleware($logger);
        $factory = new Psr17Factory();

        $middleware->handle(
            $factory->createRequest('POST', 'https://example/v1/messages'),
            static fn (RequestInterface $r): ResponseInterface => $factory->createResponse(200)->withHeader('x-request-id', 'req_xyz'),
        );

        self::assertCount(2, $logger->records);
        self::assertSame('anthropic request', $logger->records[0]['message']);
        self::assertSame('anthropic response', $logger->records[1]['message']);

        $reqContext = $logger->records[0]['context'];
        $respContext = $logger->records[1]['context'];

        self::assertSame($reqContext['correlation_id'], $respContext['correlation_id']);
        self::assertSame(200, $respContext['status']);
        self::assertSame('req_xyz', $respContext['request_id']);
    }

    public function testLogsErrorAndRethrows(): void
    {
        $logger = new RecordingLogger();
        $middleware = new LoggingMiddleware($logger);
        $factory = new Psr17Factory();

        $boom = new \RuntimeException('boom');

        try {
            $middleware->handle(
                $factory->createRequest('POST', 'https://example/'),
                static fn (RequestInterface $r): ResponseInterface => throw $boom,
            );
            self::fail('Expected RuntimeException');
        } catch (\RuntimeException $caught) {
            self::assertSame($boom, $caught);
        }

        self::assertCount(2, $logger->records);
        self::assertSame('anthropic request failed', $logger->records[1]['message']);
        self::assertSame('error', $logger->records[1]['level']);
    }

    public function testBodyOmittedByDefault(): void
    {
        $logger = new RecordingLogger();
        $middleware = new LoggingMiddleware($logger);
        $factory = new Psr17Factory();

        $request = $factory->createRequest('POST', 'https://example/')
            ->withBody($factory->createStream('{"secret":"don\'t-log-me"}'));

        $middleware->handle(
            $request,
            static fn (RequestInterface $r): ResponseInterface => $factory->createResponse(200),
        );

        self::assertNull($logger->records[0]['context']['body']);
    }

    public function testLogBodiesIncludesPayloadWhenEnabled(): void
    {
        $logger = new RecordingLogger();
        $middleware = new LoggingMiddleware($logger, logBodies: true);
        $factory = new Psr17Factory();

        $request = $factory->createRequest('POST', 'https://example/')
            ->withBody($factory->createStream('{"prompt":"hi"}'));

        $middleware->handle(
            $request,
            static fn (RequestInterface $r): ResponseInterface => $factory->createResponse(200),
        );

        self::assertSame('{"prompt":"hi"}', $logger->records[0]['context']['body']);
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => is_scalar($level) ? (string) $level : 'unknown',
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
