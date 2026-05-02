<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming;

use AlleAI\Anthropic\Messages\MessageResponse;
use AlleAI\Anthropic\Streaming\Events\StreamEvent;

/**
 * Iterator over a streamed Messages response.
 *
 * Single-pass — re-iterating yields nothing. Calling {@see EventStream::toMessage()}
 * mid-iteration drains the rest of the stream first, so it always returns
 * a complete aggregated response.
 *
 * ```php
 * $stream = $client->messages()->stream(...);
 * foreach ($stream as $event) {
 *     // observe events incrementally
 * }
 * $final = $stream->toMessage();
 * ```
 *
 * @implements \IteratorAggregate<int, StreamEvent>
 */
final class EventStream implements \IteratorAggregate
{
    private bool $consumed = false;
    private bool $finalised = false;
    private ?MessageResponse $finalMessage = null;
    private Aggregator $aggregator;

    /**
     * @param  iterable<int, string>  $chunkSource  raw SSE byte chunks
     */
    public function __construct(
        private readonly iterable $chunkSource,
        ?Aggregator $aggregator = null,
        private readonly ?EventParser $parser = null,
    ) {
        $this->aggregator = $aggregator ?? new Aggregator();
    }

    /**
     * @return \Generator<int, StreamEvent>
     */
    public function getIterator(): \Generator
    {
        if ($this->consumed) {
            return;
        }
        $this->consumed = true;

        $parser = $this->parser ?? new EventParser();

        foreach ($this->chunkSource as $chunk) {
            foreach ($parser->feed($chunk) as $event) {
                $this->aggregator->observe($event);
                yield $event;
            }
        }

        foreach ($parser->finish() as $event) {
            $this->aggregator->observe($event);
            yield $event;
        }
    }

    /**
     * Drain anything left in the stream and return the aggregated response.
     */
    public function toMessage(): MessageResponse
    {
        if (!$this->finalised) {
            // Force iteration of any unread events.
            foreach ($this as $_event) {
                // event observed by Aggregator inside getIterator()
            }
            $this->finalMessage = $this->aggregator->toMessage();
            $this->finalised = true;
        }

        /** @var MessageResponse $message */
        $message = $this->finalMessage;

        return $message;
    }
}
