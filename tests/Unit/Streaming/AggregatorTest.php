<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Streaming;

use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\ToolUseBlock;
use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Streaming\Aggregator;
use AlleAI\Anthropic\Streaming\EventParser;
use AlleAI\Anthropic\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Aggregator::class)]
final class AggregatorTest extends TestCase
{
    public function testRebuildsSimpleMessageFromStream(): void
    {
        $aggregator = new Aggregator();
        $parser = new EventParser();

        foreach ($parser->feed(Fixture::raw('streams/simple.sse')) as $event) {
            $aggregator->observe($event);
        }
        foreach ($parser->finish() as $event) {
            $aggregator->observe($event);
        }

        $message = $aggregator->toMessage();

        self::assertSame('msg_01STREAM', $message->id);
        self::assertSame('claude-sonnet-4-7', $message->model);
        self::assertSame(StopReason::END_TURN, $message->stopReason);
        self::assertSame('Hello, world!', $message->text());
        self::assertCount(1, $message->content);
        self::assertInstanceOf(TextBlock::class, $message->content[0]);

        // Final message_delta usage replaces the partial usage from message_start.
        self::assertSame(7, $message->usage->outputTokens);
        self::assertSame(12, $message->usage->inputTokens);
    }

    public function testReassemblesPartialJsonForToolUse(): void
    {
        $aggregator = new Aggregator();
        $parser = new EventParser();

        foreach ($parser->feed(Fixture::raw('streams/tool_use.sse')) as $event) {
            $aggregator->observe($event);
        }
        foreach ($parser->finish() as $event) {
            $aggregator->observe($event);
        }

        $message = $aggregator->toMessage();
        $toolUses = $message->toolUses();

        self::assertCount(1, $toolUses);
        $call = $toolUses[0];
        self::assertInstanceOf(ToolUseBlock::class, $call);
        self::assertSame('toolu_01STREAM', $call->id);
        self::assertSame('get_weather', $call->name);
        self::assertSame(['city' => 'Accra'], $call->input);
        self::assertSame(StopReason::TOOL_USE, $message->stopReason);
    }
}
