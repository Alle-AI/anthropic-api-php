<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Streaming;

use AlleAI\Anthropic\Streaming\Events\ContentBlockDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\Deltas\TextDelta;
use AlleAI\Anthropic\Streaming\EventStream;
use AlleAI\Anthropic\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventStream::class)]
final class EventStreamTest extends TestCase
{
    public function testIteratesEventsFromChunkSource(): void
    {
        $chunks = (function (): \Generator {
            // Yield the SSE in two arbitrary halves to exercise buffering.
            $raw = Fixture::raw('streams/simple.sse');
            $halfway = (int) floor(strlen($raw) / 2);
            yield substr($raw, 0, $halfway);
            yield substr($raw, $halfway);
        })();

        $stream = new EventStream($chunks);

        $textPieces = [];
        foreach ($stream as $event) {
            if ($event instanceof ContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                $textPieces[] = $event->delta->text;
            }
        }

        self::assertSame(['Hello', ', ', 'world!'], $textPieces);
    }

    public function testToMessageReturnsAggregatedResponseEvenWithoutPriorIteration(): void
    {
        $chunks = (function (): \Generator {
            yield Fixture::raw('streams/simple.sse');
        })();

        $stream = new EventStream($chunks);
        $message = $stream->toMessage();
        self::assertSame('Hello, world!', $message->text());
    }

    public function testStreamIsSinglePass(): void
    {
        $chunks = (function (): \Generator {
            yield Fixture::raw('streams/simple.sse');
        })();
        $stream = new EventStream($chunks);
        iterator_to_array($stream, false);

        $second = iterator_to_array($stream, false);
        self::assertSame([], $second);
    }
}
