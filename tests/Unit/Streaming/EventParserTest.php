<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Streaming;

use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\ToolUseBlock;
use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Streaming\EventParser;
use AlleAI\Anthropic\Streaming\Events\ContentBlockDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\ContentBlockStartEvent;
use AlleAI\Anthropic\Streaming\Events\ContentBlockStopEvent;
use AlleAI\Anthropic\Streaming\Events\Deltas\InputJsonDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\TextDelta;
use AlleAI\Anthropic\Streaming\Events\MessageDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\MessageStartEvent;
use AlleAI\Anthropic\Streaming\Events\MessageStopEvent;
use AlleAI\Anthropic\Streaming\Events\PingEvent;
use AlleAI\Anthropic\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventParser::class)]
final class EventParserTest extends TestCase
{
    public function testParsesFullSimpleStreamInOneFeed(): void
    {
        $parser = new EventParser();
        $events = iterator_to_array($parser->feed(Fixture::raw('streams/simple.sse')), false);

        self::assertCount(9, $events);
        self::assertInstanceOf(MessageStartEvent::class, $events[0]);
        self::assertInstanceOf(ContentBlockStartEvent::class, $events[1]);
        self::assertInstanceOf(PingEvent::class, $events[2]);
        self::assertInstanceOf(ContentBlockDeltaEvent::class, $events[3]);
        self::assertInstanceOf(ContentBlockStopEvent::class, $events[6]);
        self::assertInstanceOf(MessageDeltaEvent::class, $events[7]);
        self::assertInstanceOf(MessageStopEvent::class, $events[8]);

        // Verify text deltas survived parsing.
        $delta3 = $events[3];
        self::assertInstanceOf(ContentBlockDeltaEvent::class, $delta3);
        self::assertInstanceOf(TextDelta::class, $delta3->delta);
        self::assertSame('Hello', $delta3->delta->text);

        $delta4 = $events[4];
        self::assertInstanceOf(ContentBlockDeltaEvent::class, $delta4);
        self::assertInstanceOf(TextDelta::class, $delta4->delta);
        self::assertSame(', ', $delta4->delta->text);

        $delta5 = $events[5];
        self::assertInstanceOf(ContentBlockDeltaEvent::class, $delta5);
        self::assertInstanceOf(TextDelta::class, $delta5->delta);
        self::assertSame('world!', $delta5->delta->text);

        // Final message_delta carries stop_reason.
        $event7 = $events[7];
        self::assertInstanceOf(MessageDeltaEvent::class, $event7);
        self::assertSame(StopReason::END_TURN, $event7->stopReason);
    }

    public function testHandlesChunkSplitsAtArbitraryPoints(): void
    {
        $raw = Fixture::raw('streams/simple.sse');
        $parser = new EventParser();
        $events = [];

        // Feed the stream byte-by-byte; the parser must reassemble frames.
        for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
            foreach ($parser->feed($raw[$i]) as $event) {
                $events[] = $event;
            }
        }
        foreach ($parser->finish() as $event) {
            $events[] = $event;
        }

        self::assertCount(9, $events);
        self::assertInstanceOf(MessageStopEvent::class, $events[8]);
    }

    public function testHandlesCrlfLineEndings(): void
    {
        $raw = str_replace("\n", "\r\n", Fixture::raw('streams/simple.sse'));
        $parser = new EventParser();
        $events = iterator_to_array($parser->feed($raw), false);
        self::assertCount(9, $events);
    }

    public function testParsesToolUseStreamWithInputJsonDelta(): void
    {
        $parser = new EventParser();
        $events = iterator_to_array($parser->feed(Fixture::raw('streams/tool_use.sse')), false);

        $startBlock = null;
        $jsonDeltas = [];
        foreach ($events as $event) {
            if ($event instanceof ContentBlockStartEvent) {
                $startBlock = $event->contentBlock;
            }
            if ($event instanceof ContentBlockDeltaEvent && $event->delta instanceof InputJsonDelta) {
                $jsonDeltas[] = $event->delta->partialJson;
            }
        }

        self::assertInstanceOf(ToolUseBlock::class, $startBlock);
        self::assertSame(['{"city":', ' "Accra"}'], $jsonDeltas);
    }

    public function testParsesContentBlockStartAsTypedBlock(): void
    {
        $parser = new EventParser();
        $events = iterator_to_array($parser->feed(Fixture::raw('streams/simple.sse')), false);
        /** @var ContentBlockStartEvent $start */
        $start = $events[1];
        self::assertInstanceOf(TextBlock::class, $start->contentBlock);
    }

    public function testIgnoresCommentLines(): void
    {
        $sse = ":heartbeat\n\nevent: ping\ndata: {\"type\":\"ping\"}\n\n";
        $parser = new EventParser();
        $events = iterator_to_array($parser->feed($sse), false);
        self::assertCount(1, $events);
        self::assertInstanceOf(PingEvent::class, $events[0]);
    }
}
