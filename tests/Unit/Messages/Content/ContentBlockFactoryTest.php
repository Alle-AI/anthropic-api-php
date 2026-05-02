<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Messages\Content;

use AlleAI\Anthropic\Messages\Content\ContentBlockFactory;
use AlleAI\Anthropic\Messages\Content\ImageBlock;
use AlleAI\Anthropic\Messages\Content\RedactedThinkingBlock;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\ThinkingBlock;
use AlleAI\Anthropic\Messages\Content\ToolResultBlock;
use AlleAI\Anthropic\Messages\Content\ToolUseBlock;
use AlleAI\Anthropic\Messages\Content\UnknownBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentBlockFactory::class)]
final class ContentBlockFactoryTest extends TestCase
{
    public function testParsesTextBlock(): void
    {
        $block = ContentBlockFactory::fromArray(['type' => 'text', 'text' => 'hi']);
        self::assertInstanceOf(TextBlock::class, $block);
        self::assertSame('hi', $block->text);
    }

    public function testParsesUrlImageBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'image',
            'source' => ['type' => 'url', 'url' => 'https://x.test/cat.png'],
        ]);
        self::assertInstanceOf(ImageBlock::class, $block);
        self::assertSame('url', $block->sourceType);
        self::assertSame('https://x.test/cat.png', $block->data);
    }

    public function testParsesBase64ImageBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'image',
            'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => 'AAAA'],
        ]);
        self::assertInstanceOf(ImageBlock::class, $block);
        self::assertSame('base64', $block->sourceType);
        self::assertSame('image/jpeg', $block->mediaType);
        self::assertSame('AAAA', $block->data);
    }

    public function testParsesToolUseBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'tool_use',
            'id' => 'toolu_1',
            'name' => 'get_weather',
            'input' => ['city' => 'Accra'],
        ]);
        self::assertInstanceOf(ToolUseBlock::class, $block);
        self::assertSame('toolu_1', $block->id);
        self::assertSame(['city' => 'Accra'], $block->input);
    }

    public function testParsesToolResultBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'tool_result',
            'tool_use_id' => 'toolu_1',
            'content' => 'oops',
            'is_error' => true,
        ]);
        self::assertInstanceOf(ToolResultBlock::class, $block);
        self::assertTrue($block->isError);
    }

    public function testParsesThinkingBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'thinking',
            'thinking' => 'reasoning',
            'signature' => 'sig',
        ]);
        self::assertInstanceOf(ThinkingBlock::class, $block);
        self::assertSame('sig', $block->signature);
    }

    public function testParsesRedactedThinking(): void
    {
        $block = ContentBlockFactory::fromArray(['type' => 'redacted_thinking', 'data' => 'opaque']);
        self::assertInstanceOf(RedactedThinkingBlock::class, $block);
        self::assertSame('opaque', $block->data);
    }

    public function testFallsBackToUnknownBlock(): void
    {
        $raw = ['type' => 'some_future_type', 'foo' => 'bar'];
        $block = ContentBlockFactory::fromArray($raw);
        self::assertInstanceOf(UnknownBlock::class, $block);
        self::assertSame('some_future_type', $block->type());
        self::assertSame($raw, $block->toArray());
    }
}
