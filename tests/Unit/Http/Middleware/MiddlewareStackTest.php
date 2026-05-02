<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Http\Middleware;

use AlleAI\Anthropic\Http\Middleware\Middleware;
use AlleAI\Anthropic\Http\Middleware\MiddlewareStack;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(MiddlewareStack::class)]
final class MiddlewareStackTest extends TestCase
{
    public function testMiddlewareRunsInOnionOrder(): void
    {
        $log = [];
        $factory = new Psr17Factory();

        $outer = new class ($log) implements Middleware {
            /** @param  array<int, string> $log */
            public function __construct(private array &$log) {}
            public function handle(RequestInterface $request, callable $next): ResponseInterface
            {
                $this->log[] = 'outer:before';
                $response = $next($request->withHeader('X-Outer', '1'));
                $this->log[] = 'outer:after';

                return $response;
            }
        };

        $inner = new class ($log) implements Middleware {
            /** @param  array<int, string> $log */
            public function __construct(private array &$log) {}
            public function handle(RequestInterface $request, callable $next): ResponseInterface
            {
                $this->log[] = 'inner:before';
                self::assertSame('1', $request->getHeaderLine('X-Outer'));
                $response = $next($request->withHeader('X-Inner', '1'));
                $this->log[] = 'inner:after';

                return $response;
            }
        };

        $stack = new MiddlewareStack(static function (RequestInterface $request) use ($factory, &$log): ResponseInterface {
            $log[] = 'handler';
            self::assertSame('1', $request->getHeaderLine('X-Outer'));
            self::assertSame('1', $request->getHeaderLine('X-Inner'));

            return $factory->createResponse(200);
        });

        $stack->push($outer)->push($inner);
        $stack->handle($factory->createRequest('POST', 'https://example/'));

        self::assertSame(
            ['outer:before', 'inner:before', 'handler', 'inner:after', 'outer:after'],
            $log,
        );
    }
}
