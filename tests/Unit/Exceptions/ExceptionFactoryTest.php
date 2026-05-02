<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Exceptions;

use AlleAI\Anthropic\Exceptions\ApiException;
use AlleAI\Anthropic\Exceptions\AuthenticationException;
use AlleAI\Anthropic\Exceptions\BadRequestException;
use AlleAI\Anthropic\Exceptions\ExceptionFactory;
use AlleAI\Anthropic\Exceptions\InternalServerException;
use AlleAI\Anthropic\Exceptions\NotFoundException;
use AlleAI\Anthropic\Exceptions\OverloadedException;
use AlleAI\Anthropic\Exceptions\PermissionDeniedException;
use AlleAI\Anthropic\Exceptions\RateLimitException;
use AlleAI\Anthropic\Exceptions\RequestTooLargeException;
use AlleAI\Anthropic\Exceptions\UnprocessableEntityException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(ExceptionFactory::class)]
final class ExceptionFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function statusToClass(): iterable
    {
        yield '400 → BadRequestException' => [400, BadRequestException::class];
        yield '401 → AuthenticationException' => [401, AuthenticationException::class];
        yield '403 → PermissionDeniedException' => [403, PermissionDeniedException::class];
        yield '404 → NotFoundException' => [404, NotFoundException::class];
        yield '413 → RequestTooLargeException' => [413, RequestTooLargeException::class];
        yield '422 → UnprocessableEntityException' => [422, UnprocessableEntityException::class];
        yield '429 → RateLimitException' => [429, RateLimitException::class];
        yield '500 → InternalServerException' => [500, InternalServerException::class];
        yield '502 → InternalServerException' => [502, InternalServerException::class];
        yield '529 → OverloadedException' => [529, OverloadedException::class];
        yield '418 → generic ApiException' => [418, ApiException::class];
    }

    /**
     * @param  class-string<ApiException>  $expected
     */
    #[DataProvider('statusToClass')]
    public function testStatusCodesMapToExceptionClasses(int $status, string $expected): void
    {
        $response = $this->responseWithBody($status, ['type' => 'error', 'error' => ['type' => 't', 'message' => 'm']]);
        $exception = ExceptionFactory::fromResponse($response);

        self::assertInstanceOf($expected, $exception);
        self::assertSame($status, $exception->status);
    }

    public function testExtractsErrorMessageAndType(): void
    {
        $response = $this->responseWithBody(400, [
            'type' => 'error',
            'error' => ['type' => 'invalid_request_error', 'message' => 'bad model id'],
        ]);
        $exception = ExceptionFactory::fromResponse($response);

        self::assertSame('invalid_request_error', $exception->errorType);
        self::assertStringContainsString('bad model id', $exception->getMessage());
    }

    public function testTolerantToNonJsonBody(): void
    {
        $response = $this->rawResponse(500, '<html>upstream error</html>');
        $exception = ExceptionFactory::fromResponse($response);

        self::assertInstanceOf(InternalServerException::class, $exception);
        self::assertSame('<html>upstream error</html>', $exception->rawBody);
        self::assertNull($exception->errorType);
    }

    public function testCapturesRequestId(): void
    {
        $response = $this->responseWithBody(429, ['type' => 'error', 'error' => ['type' => 'rate_limit_error']])
            ->withHeader('x-request-id', 'req_abc123');

        $exception = ExceptionFactory::fromResponse($response);

        self::assertSame('req_abc123', $exception->requestId);
    }

    public function testRateLimitExceptionParsesRetryAfter(): void
    {
        $response = $this->responseWithBody(429, ['type' => 'error', 'error' => ['type' => 'rate_limit_error']])
            ->withHeader('Retry-After', '17');

        /** @var RateLimitException $exception */
        $exception = ExceptionFactory::fromResponse($response);

        self::assertSame(17, $exception->retryAfter());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function responseWithBody(int $status, array $body): ResponseInterface
    {
        $factory = new Psr17Factory();

        return $factory->createResponse($status)
            ->withBody($factory->createStream(json_encode($body, JSON_THROW_ON_ERROR)))
            ->withHeader('content-type', 'application/json');
    }

    private function rawResponse(int $status, string $body): ResponseInterface
    {
        $factory = new Psr17Factory();

        return $factory->createResponse($status)->withBody($factory->createStream($body));
    }
}
