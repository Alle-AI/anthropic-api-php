<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Tools\Server;

use AlleAI\Anthropic\Tools\Server\BashToolDefinition;
use AlleAI\Anthropic\Tools\Server\ComputerToolDefinition;
use AlleAI\Anthropic\Tools\Server\TextEditorToolDefinition;
use AlleAI\Anthropic\Tools\Server\WebSearchToolDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebSearchToolDefinition::class)]
#[CoversClass(ComputerToolDefinition::class)]
#[CoversClass(BashToolDefinition::class)]
#[CoversClass(TextEditorToolDefinition::class)]
final class ServerToolDefinitionsTest extends TestCase
{
    public function testWebSearchMinimal(): void
    {
        self::assertSame(
            ['type' => 'web_search_20250305', 'name' => 'web_search'],
            WebSearchToolDefinition::create()->toArray(),
        );
    }

    public function testWebSearchWithMaxUsesAndAllowedDomains(): void
    {
        $tool = WebSearchToolDefinition::create(
            maxUses: 3,
            allowedDomains: ['example.com', 'wikipedia.org'],
        );
        $arr = $tool->toArray();
        self::assertSame(3, $arr['max_uses']);
        self::assertSame(['example.com', 'wikipedia.org'], $arr['allowed_domains']);
        self::assertArrayNotHasKey('blocked_domains', $arr);
    }

    public function testWebSearchRejectsBothAllowedAndBlocked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebSearchToolDefinition::create(
            allowedDomains: ['a.com'],
            blockedDomains: ['b.com'],
        );
    }

    public function testWebSearchExposesBetaHeader(): void
    {
        self::assertSame('web-search-2025-03-05', WebSearchToolDefinition::create()->betaHeader());
    }

    public function testComputerToolDefinition(): void
    {
        $tool = ComputerToolDefinition::create(
            displayWidthPx: 1024,
            displayHeightPx: 768,
            displayNumber: 1,
        );
        self::assertSame([
            'type' => 'computer_20250124',
            'name' => 'computer',
            'display_width_px' => 1024,
            'display_height_px' => 768,
            'display_number' => 1,
        ], $tool->toArray());
        self::assertSame('computer-use-2025-01-24', $tool->betaHeader());
    }

    public function testComputerToolRejectsNonPositiveDimensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ComputerToolDefinition::create(displayWidthPx: 0, displayHeightPx: 768);
    }

    public function testBashToolDefinition(): void
    {
        $tool = BashToolDefinition::create();
        self::assertSame(['type' => 'bash_20250124', 'name' => 'bash'], $tool->toArray());
    }

    public function testTextEditorToolDefinition(): void
    {
        $tool = TextEditorToolDefinition::create();
        self::assertSame(['type' => 'text_editor_20250124', 'name' => 'str_replace_editor'], $tool->toArray());
    }
}
