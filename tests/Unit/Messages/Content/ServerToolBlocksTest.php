<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Messages\Content;

use AlleAI\Anthropic\Messages\Content\ContentBlockFactory;
use AlleAI\Anthropic\Messages\Content\ServerToolUseBlock;
use AlleAI\Anthropic\Messages\Content\WebSearchToolResultBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerToolUseBlock::class)]
#[CoversClass(WebSearchToolResultBlock::class)]
#[CoversClass(ContentBlockFactory::class)]
final class ServerToolBlocksTest extends TestCase
{
    public function testFactoryParsesServerToolUse(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'server_tool_use',
            'id' => 'srvtoolu_1',
            'name' => 'web_search',
            'input' => ['query' => 'AI news'],
        ]);
        self::assertInstanceOf(ServerToolUseBlock::class, $block);
        self::assertSame('srvtoolu_1', $block->id);
        self::assertSame('web_search', $block->name);
        self::assertSame(['query' => 'AI news'], $block->input);
    }

    public function testFactoryParsesWebSearchToolResultSuccess(): void
    {
        $results = [
            ['type' => 'web_search_result', 'url' => 'https://a/', 'title' => 'A', 'encrypted_content' => 'enc-a'],
            ['type' => 'web_search_result', 'url' => 'https://b/', 'title' => 'B', 'encrypted_content' => 'enc-b'],
        ];
        $block = ContentBlockFactory::fromArray([
            'type' => 'web_search_tool_result',
            'tool_use_id' => 'srvtoolu_1',
            'content' => $results,
        ]);
        self::assertInstanceOf(WebSearchToolResultBlock::class, $block);
        self::assertFalse($block->isError());
        self::assertNull($block->errorCode());
        self::assertCount(2, $block->results());
    }

    public function testFactoryParsesWebSearchToolResultError(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'web_search_tool_result',
            'tool_use_id' => 'srvtoolu_2',
            'content' => [
                'type' => 'web_search_tool_result_error',
                'error_code' => 'too_many_requests',
            ],
        ]);
        self::assertInstanceOf(WebSearchToolResultBlock::class, $block);
        self::assertTrue($block->isError());
        self::assertSame('too_many_requests', $block->errorCode());
        self::assertSame([], $block->results());
    }

    public function testServerToolUseBlockSerialisesRoundTrip(): void
    {
        $block = new ServerToolUseBlock('id_1', 'web_search', ['query' => 'x']);
        self::assertSame([
            'type' => 'server_tool_use',
            'id' => 'id_1',
            'name' => 'web_search',
            'input' => ['query' => 'x'],
        ], $block->toArray());
    }
}
