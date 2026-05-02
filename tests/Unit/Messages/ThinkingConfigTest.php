<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Messages;

use AlleAI\Anthropic\Messages\ThinkingConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThinkingConfig::class)]
final class ThinkingConfigTest extends TestCase
{
    public function testEnabledRequiresMinimumBudget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ThinkingConfig::enabled(budgetTokens: 100);
    }

    public function testEnabledSerialises(): void
    {
        self::assertSame(
            ['type' => 'enabled', 'budget_tokens' => 4096],
            ThinkingConfig::enabled(4096)->toArray(),
        );
    }

    public function testDisabledOmitsBudget(): void
    {
        self::assertSame(['type' => 'disabled'], ThinkingConfig::disabled()->toArray());
    }
}
