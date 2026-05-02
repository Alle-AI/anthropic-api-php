<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Messages;

use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\ToolUseBlock;
use AlleAI\Anthropic\Messages\MessageResponse;
use AlleAI\Anthropic\Messages\Role;
use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageResponse::class)]
final class MessageResponseTest extends TestCase
{
    public function testParsesSimpleFixture(): void
    {
        $response = MessageResponse::fromArray(Fixture::json('messages/simple.json'));

        self::assertSame('msg_01ABCDEF', $response->id);
        self::assertSame(Role::ASSISTANT, $response->role);
        self::assertSame('claude-sonnet-4-7', $response->model);
        self::assertSame(StopReason::END_TURN, $response->stopReason);
        self::assertSame(12, $response->usage->inputTokens);
        self::assertSame(9, $response->usage->outputTokens);
        self::assertCount(1, $response->content);
        self::assertInstanceOf(TextBlock::class, $response->content[0]);
        self::assertSame('Hello! How can I help you today?', $response->text());
        self::assertFalse($response->hasToolUse());
        self::assertSame([], $response->toolUses());
    }

    public function testParsesToolUseFixture(): void
    {
        $response = MessageResponse::fromArray(Fixture::json('messages/tool_use.json'));

        self::assertSame(StopReason::TOOL_USE, $response->stopReason);
        self::assertTrue($response->hasToolUse());
        self::assertCount(1, $response->toolUses());
        self::assertInstanceOf(ToolUseBlock::class, $response->toolUses()[0]);
        self::assertSame('get_weather', $response->toolUses()[0]->name);
        self::assertSame(['city' => 'Accra', 'units' => 'c'], $response->toolUses()[0]->input);
        self::assertSame('Let me check the weather for you.', $response->text());
    }

    public function testRawIsExposed(): void
    {
        $raw = Fixture::json('messages/simple.json');
        $response = MessageResponse::fromArray($raw);
        self::assertSame($raw, $response->raw);
    }

    public function testUnknownStopReasonBecomesNull(): void
    {
        $response = MessageResponse::fromArray([
            'id' => 'msg_x',
            'role' => 'assistant',
            'content' => [],
            'model' => 'claude-x',
            'stop_reason' => 'some_future_reason',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]);
        self::assertNull($response->stopReason);
    }
}
