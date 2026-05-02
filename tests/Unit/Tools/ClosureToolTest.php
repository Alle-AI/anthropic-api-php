<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Tools;

use AlleAI\Anthropic\Messages\Content\CacheControl;
use AlleAI\Anthropic\Tools\ClosureTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClosureTool::class)]
final class ClosureToolTest extends TestCase
{
    public function testToArrayContainsNameDescriptionAndSchema(): void
    {
        $tool = ClosureTool::create(
            name: 'echo',
            description: 'Echo input',
            schema: ['type' => 'object', 'properties' => []],
            handler: static fn (array $i): mixed => $i,
        );

        self::assertSame([
            'name' => 'echo',
            'description' => 'Echo input',
            'input_schema' => ['type' => 'object', 'properties' => []],
        ], $tool->toArray());
    }

    public function testRunInvokesHandler(): void
    {
        $tool = ClosureTool::create(
            name: 't',
            description: 't',
            schema: ['type' => 'object'],
            handler: static fn (array $i): array => ['got' => $i],
        );

        self::assertSame(['got' => ['x' => 1]], $tool->run(['x' => 1]));
    }

    public function testCacheControlIncludedInToArray(): void
    {
        $tool = ClosureTool::create(
            name: 't',
            description: 't',
            schema: ['type' => 'object'],
            handler: static fn (array $i): mixed => null,
            cacheControl: CacheControl::ephemeral('1h'),
        );
        $arr = $tool->toArray();
        self::assertSame(['type' => 'ephemeral', 'ttl' => '1h'], $arr['cache_control']);
    }
}
