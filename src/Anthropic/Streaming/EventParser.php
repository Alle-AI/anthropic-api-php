<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming;

use AlleAI\Anthropic\Exceptions\StreamException;
use AlleAI\Anthropic\Messages\Content\ContentBlockFactory;
use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Messages\Usage;
use AlleAI\Anthropic\Streaming\Events\ContentBlockDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\ContentBlockStartEvent;
use AlleAI\Anthropic\Streaming\Events\ContentBlockStopEvent;
use AlleAI\Anthropic\Streaming\Events\Deltas\CitationsDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\Delta;
use AlleAI\Anthropic\Streaming\Events\Deltas\InputJsonDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\SignatureDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\TextDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\ThinkingDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\UnknownDelta;
use AlleAI\Anthropic\Streaming\Events\ErrorEvent;
use AlleAI\Anthropic\Streaming\Events\MessageDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\MessageStartEvent;
use AlleAI\Anthropic\Streaming\Events\MessageStopEvent;
use AlleAI\Anthropic\Streaming\Events\PingEvent;
use AlleAI\Anthropic\Streaming\Events\StreamEvent;
use AlleAI\Anthropic\Streaming\Events\UnknownEvent;
use AlleAI\Anthropic\Util\Json;

/**
 * Streaming SSE parser.
 *
 * Consumes raw SSE chunks (which may split across `\n\n` event boundaries
 * arbitrarily) and yields typed {@see StreamEvent}s as complete frames
 * become available. Stateful — instantiate one parser per stream.
 *
 * Usage:
 *
 * ```php
 * $parser = new EventParser();
 * foreach ($transport->stream($request) as $chunk) {
 *     foreach ($parser->feed($chunk) as $event) {
 *         // ...
 *     }
 * }
 * foreach ($parser->finish() as $event) { ... }
 * ```
 */
final class EventParser
{
    private string $buffer = '';

    /**
     * Append `$chunk` to the internal buffer and yield any complete events
     * that emerge.
     *
     * @return \Generator<int, StreamEvent>
     */
    public function feed(string $chunk): \Generator
    {
        // Normalize CRLF → LF so the framing rules below are deterministic.
        $chunk = str_replace("\r\n", "\n", $chunk);
        $this->buffer .= $chunk;

        // SSE frames are separated by a blank line (`\n\n`).
        while (($pos = strpos($this->buffer, "\n\n")) !== false) {
            $frame = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 2);

            $event = $this->parseFrame($frame);
            if ($event !== null) {
                yield $event;
            }
        }
    }

    /**
     * Drain anything left in the buffer at end-of-stream. Most servers send
     * a trailing `\n\n` so this is usually a no-op.
     *
     * @return \Generator<int, StreamEvent>
     */
    public function finish(): \Generator
    {
        $remaining = trim($this->buffer);
        $this->buffer = '';

        if ($remaining === '') {
            return;
        }

        $event = $this->parseFrame($remaining);
        if ($event !== null) {
            yield $event;
        }
    }

    private function parseFrame(string $frame): ?StreamEvent
    {
        $eventName = null;
        $dataLines = [];

        foreach (explode("\n", $frame) as $line) {
            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            $colon = strpos($line, ':');
            if ($colon === false) {
                $field = $line;
                $value = '';
            } else {
                $field = substr($line, 0, $colon);
                $value = substr($line, $colon + 1);
                if (str_starts_with($value, ' ')) {
                    $value = substr($value, 1);
                }
            }

            match ($field) {
                'event' => $eventName = $value,
                'data' => $dataLines[] = $value,
                default => null, // ignore id/retry — Anthropic doesn't use them
            };
        }

        if ($dataLines === []) {
            return null;
        }

        $data = implode("\n", $dataLines);
        $payload = Json::decode($data);

        return $this->buildEvent($eventName, $payload);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function buildEvent(?string $eventName, array $payload): StreamEvent
    {
        $type = $eventName
            ?? (isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : null);

        return match ($type) {
            'message_start' => new MessageStartEvent(
                /** @phpstan-ignore-next-line — defensive cast */
                isset($payload['message']) && is_array($payload['message']) ? $payload['message'] : [],
            ),
            'content_block_start' => $this->buildContentBlockStart($payload),
            'content_block_delta' => $this->buildContentBlockDelta($payload),
            'content_block_stop' => new ContentBlockStopEvent(
                index: isset($payload['index']) && is_numeric($payload['index']) ? (int) $payload['index'] : 0,
            ),
            'message_delta' => $this->buildMessageDelta($payload),
            'message_stop' => new MessageStopEvent(),
            'ping' => new PingEvent(),
            'error' => $this->buildError($payload),
            default => new UnknownEvent($type ?? '', self::asStringMap($payload)),
        };
    }

    /**
     * @param  array<array-key, mixed>  $raw
     *
     * @return array<string, mixed>
     */
    private static function asStringMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function buildContentBlockStart(array $payload): ContentBlockStartEvent
    {
        $index = isset($payload['index']) && is_numeric($payload['index']) ? (int) $payload['index'] : 0;
        $blockRaw = isset($payload['content_block']) && is_array($payload['content_block'])
            ? $payload['content_block']
            : [];

        /** @var array<string, mixed> $blockRaw */
        return new ContentBlockStartEvent(
            index: $index,
            contentBlock: ContentBlockFactory::fromArray($blockRaw),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function buildContentBlockDelta(array $payload): ContentBlockDeltaEvent
    {
        $index = isset($payload['index']) && is_numeric($payload['index']) ? (int) $payload['index'] : 0;
        $deltaRaw = isset($payload['delta']) && is_array($payload['delta']) ? $payload['delta'] : [];

        /** @var array<string, mixed> $deltaRaw */
        return new ContentBlockDeltaEvent(
            index: $index,
            delta: $this->buildDelta($deltaRaw),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function buildDelta(array $raw): Delta
    {
        $type = isset($raw['type']) && is_string($raw['type']) ? $raw['type'] : '';

        return match ($type) {
            'text_delta' => new TextDelta(
                isset($raw['text']) && is_string($raw['text']) ? $raw['text'] : '',
            ),
            'input_json_delta' => new InputJsonDelta(
                isset($raw['partial_json']) && is_string($raw['partial_json']) ? $raw['partial_json'] : '',
            ),
            'thinking_delta' => new ThinkingDelta(
                isset($raw['thinking']) && is_string($raw['thinking']) ? $raw['thinking'] : '',
            ),
            'signature_delta' => new SignatureDelta(
                isset($raw['signature']) && is_string($raw['signature']) ? $raw['signature'] : '',
            ),
            'citations_delta' => new CitationsDelta(
                /** @phpstan-ignore-next-line — defensive cast */
                isset($raw['citation']) && is_array($raw['citation']) ? $raw['citation'] : [],
            ),
            default => new UnknownDelta($type, $raw),
        };
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function buildMessageDelta(array $payload): MessageDeltaEvent
    {
        $delta = isset($payload['delta']) && is_array($payload['delta']) ? $payload['delta'] : [];
        $usageRaw = isset($payload['usage']) && is_array($payload['usage']) ? $payload['usage'] : null;

        $stopReasonStr = isset($delta['stop_reason']) && is_string($delta['stop_reason'])
            ? $delta['stop_reason']
            : null;
        $stopSequence = isset($delta['stop_sequence']) && is_string($delta['stop_sequence'])
            ? $delta['stop_sequence']
            : null;

        return new MessageDeltaEvent(
            stopReason: StopReason::tryFromString($stopReasonStr),
            stopSequence: $stopSequence,
            usage: $usageRaw !== null ? Usage::fromArray($usageRaw) : null,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function buildError(array $payload): ErrorEvent
    {
        $error = isset($payload['error']) && is_array($payload['error']) ? $payload['error'] : [];
        $type = isset($error['type']) && is_string($error['type']) ? $error['type'] : 'unknown_error';
        $message = isset($error['message']) && is_string($error['message']) ? $error['message'] : '';

        return new ErrorEvent($type, $message);
    }

    /**
     * @internal  for tests
     */
    public function bufferLength(): int
    {
        return strlen($this->buffer);
    }

    /**
     * @internal  for tests
     */
    public function ensureClean(): void
    {
        if ($this->buffer !== '') {
            throw new StreamException(sprintf(
                'Stream ended with unparsed buffer (%d bytes).',
                strlen($this->buffer),
            ));
        }
    }
}
