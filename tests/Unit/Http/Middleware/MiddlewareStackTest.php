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
        $observed = [];
        $factory = new Psr17Factory();

        $tag = static function (string $event) use (&$log): void {
            $log[] = $event;
        };
        $observe = static function (string $key, mixed $value) use (&$observed): void {
            $observed[$key] = $value;
        };

        $outer = new class ($tag) implements Middleware {
            /** @param  callable(string): void  $tag */
            public function __construct(private $tag)
            {
            }
            public function handle(RequestInterface $request, callable $next): ResponseInterface
            {
                ($this->tag)('outer:before');
                $response = $next($request->withHeader('X-Outer', '1'));
                ($this->tag)('outer:after');

                return $response;
            }
        };

        $inner = new class ($tag, $observe) implements Middleware {
            /**
             * @param  callable(string): void  $tag
             * @param  callable(string, mixed): void  $observe
             */
            public function __construct(private $tag, private $observe)
            {
            }
            public function handle(RequestInterface $request, callable $next): ResponseInterface
            {
                ($this->tag)('inner:before');
                ($this->observe)('inner_saw_outer', $request->getHeaderLine('X-Outer'));
                $response = $next($request->withHeader('X-Inner', '1'));
                ($this->tag)('inner:after');

                return $response;
            }
        };

        $stack = new MiddlewareStack(static function (RequestInterface $request) use ($factory, $tag, $observe): ResponseInterface {
            $tag('handler');
            $observe('handler_saw_outer', $request->getHeaderLine('X-Outer'));
            $observe('handler_saw_inner', $request->getHeaderLine('X-Inner'));

            return $factory->createResponse(200);
        });

        $stack->push($outer)->push($inner);
        $stack->handle($factory->createRequest('POST', 'https://example/'));

        self::assertSame(
            ['outer:before', 'inner:before', 'handler', 'inner:after', 'outer:after'],
            $log,
        );
        self::assertSame('1', $observed['inner_saw_outer']);
        self::assertSame('1', $observed['handler_saw_outer']);
        self::assertSame('1', $observed['handler_saw_inner']);
    }
}
