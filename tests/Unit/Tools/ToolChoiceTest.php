<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Tools;

use AlleAI\Anthropic\Tools\ToolChoice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolChoice::class)]
final class ToolChoiceTest extends TestCase
{
    public function testAuto(): void
    {
        self::assertSame(['type' => 'auto'], ToolChoice::auto()->toArray());
    }

    public function testAnyWithDisableParallel(): void
    {
        self::assertSame(
            ['type' => 'any', 'disable_parallel_tool_use' => true],
            ToolChoice::any(true)->toArray(),
        );
    }

    public function testTool(): void
    {
        self::assertSame(['type' => 'tool', 'name' => 'go'], ToolChoice::tool('go')->toArray());
    }

    public function testNone(): void
    {
        self::assertSame(['type' => 'none'], ToolChoice::none()->toArray());
    }
}
