<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Tools;

use AlleAI\Anthropic\Tools\ClosureTool;
use AlleAI\Anthropic\Tools\ToolSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolSet::class)]
final class ToolSetTest extends TestCase
{
    public function testHoldsToolsByName(): void
    {
        $a = ClosureTool::create('a', 'a', ['type' => 'object'], static fn (array $i): mixed => null);
        $b = ClosureTool::create('b', 'b', ['type' => 'object'], static fn (array $i): mixed => null);

        $set = new ToolSet($a, $b);
        self::assertTrue($set->has('a'));
        self::assertTrue($set->has('b'));
        self::assertSame($a, $set->get('a'));
        self::assertCount(2, $set->all());
    }

    public function testRejectsDuplicates(): void
    {
        $a = ClosureTool::create('dupe', 'a', ['type' => 'object'], static fn (array $i): mixed => null);
        $a2 = ClosureTool::create('dupe', 'b', ['type' => 'object'], static fn (array $i): mixed => null);

        $this->expectException(\InvalidArgumentException::class);
        new ToolSet($a, $a2);
    }

    public function testGetThrowsForUnknown(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        (new ToolSet())->get('missing');
    }

    public function testIsEmpty(): void
    {
        self::assertTrue((new ToolSet())->isEmpty());
    }
}
