<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Messages\Content;

use AlleAI\Anthropic\Messages\Content\CacheControl;
use AlleAI\Anthropic\Messages\Content\ImageBlock;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\ThinkingBlock;
use AlleAI\Anthropic\Messages\Content\ToolResultBlock;
use AlleAI\Anthropic\Messages\Content\ToolUseBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextBlock::class)]
#[CoversClass(CacheControl::class)]
#[CoversClass(ImageBlock::class)]
#[CoversClass(ToolUseBlock::class)]
#[CoversClass(ToolResultBlock::class)]
#[CoversClass(ThinkingBlock::class)]
final class ContentBlockTest extends TestCase
{
    public function testTextBlockSerialisesWithoutCacheControl(): void
    {
        $block = TextBlock::of('hello');
        self::assertSame(['type' => 'text', 'text' => 'hello'], $block->toArray());
        self::assertSame('text', $block->type());
    }

    public function testTextBlockWithCacheControl(): void
    {
        $block = TextBlock::of('hello')->withCacheControl(CacheControl::ephemeral('1h'));

        self::assertSame([
            'type' => 'text',
            'text' => 'hello',
            'cache_control' => ['type' => 'ephemeral', 'ttl' => '1h'],
        ], $block->toArray());
    }

    public function testCacheControlEphemeralWithoutTtlOmitsField(): void
    {
        self::assertSame(['type' => 'ephemeral'], CacheControl::ephemeral()->toArray());
    }

    public function testImageBlockFromUrl(): void
    {
        $block = ImageBlock::fromUrl('https://example.com/cat.png');
        self::assertSame([
            'type' => 'image',
            'source' => ['type' => 'url', 'url' => 'https://example.com/cat.png'],
        ], $block->toArray());
    }

    public function testImageBlockFromBase64(): void
    {
        $block = ImageBlock::fromBase64('AAAA', 'image/png');
        self::assertSame([
            'type' => 'image',
            'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'AAAA'],
        ], $block->toArray());
    }

    public function testImageBlockFromFileReadsAndDetectsMime(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img') . '.png';
        // 1x1 transparent PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=');
        file_put_contents($tmp, $png);

        try {
            $block = ImageBlock::fromFile($tmp);
            $arr = $block->toArray();
            self::assertSame('base64', $arr['source']['type']);
            self::assertSame('image/png', $arr['source']['media_type']);
            self::assertSame(base64_encode($png), $arr['source']['data']);
        } finally {
            @unlink($tmp);
        }
    }

    public function testImageBlockFromFileThrowsWhenMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ImageBlock::fromFile('/nonexistent/__never__.png');
    }

    public function testToolUseBlockSerialises(): void
    {
        $block = new ToolUseBlock('toolu_1', 'get_weather', ['city' => 'Accra']);
        self::assertSame([
            'type' => 'tool_use',
            'id' => 'toolu_1',
            'name' => 'get_weather',
            'input' => ['city' => 'Accra'],
        ], $block->toArray());
    }

    public function testToolResultBlockOk(): void
    {
        $block = ToolResultBlock::ok('toolu_1', ['ok' => true]);
        self::assertSame([
            'type' => 'tool_result',
            'tool_use_id' => 'toolu_1',
            'content' => ['ok' => true],
        ], $block->toArray());
    }

    public function testToolResultBlockError(): void
    {
        $block = ToolResultBlock::error('toolu_1', 'oops');
        self::assertSame([
            'type' => 'tool_result',
            'tool_use_id' => 'toolu_1',
            'content' => 'oops',
            'is_error' => true,
        ], $block->toArray());
    }

    public function testThinkingBlockSerialises(): void
    {
        $block = new ThinkingBlock('reasoning text', 'sig');
        self::assertSame([
            'type' => 'thinking',
            'thinking' => 'reasoning text',
            'signature' => 'sig',
        ], $block->toArray());
    }

    public function testThinkingBlockWithoutSignature(): void
    {
        $block = new ThinkingBlock('reasoning text');
        self::assertSame(['type' => 'thinking', 'thinking' => 'reasoning text'], $block->toArray());
    }
}
