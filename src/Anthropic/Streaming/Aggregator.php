<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming;

use AlleAI\Anthropic\Exceptions\StreamException;
use AlleAI\Anthropic\Messages\Content\ContentBlock;
use AlleAI\Anthropic\Messages\Content\ContentBlockFactory;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\ThinkingBlock;
use AlleAI\Anthropic\Messages\Content\ToolUseBlock;
use AlleAI\Anthropic\Messages\MessageResponse;
use AlleAI\Anthropic\Messages\Role;
use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Messages\Usage;
use AlleAI\Anthropic\Streaming\Events\ContentBlockDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\ContentBlockStartEvent;
use AlleAI\Anthropic\Streaming\Events\Deltas\InputJsonDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\SignatureDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\TextDelta;
use AlleAI\Anthropic\Streaming\Events\Deltas\ThinkingDelta;
use AlleAI\Anthropic\Streaming\Events\ErrorEvent;
use AlleAI\Anthropic\Streaming\Events\MessageDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\MessageStartEvent;
use AlleAI\Anthropic\Streaming\Events\StreamEvent;
use AlleAI\Anthropic\Util\Json;

/**
 * Builds a final {@see MessageResponse} from a sequence of streaming events.
 *
 * Feed every event in order via {@see Aggregator::observe()}, then call
 * {@see Aggregator::toMessage()} once the stream has ended. The resulting
 * message is indistinguishable from one returned by a non-streamed
 * `Messages::create()` call for the same prompt.
 */
final class Aggregator
{
    private string $id = '';
    private Role $role = Role::ASSISTANT;
    private string $model = '';
    private ?StopReason $stopReason = null;
    private ?string $stopSequence = null;
    private Usage $usage;

    /**
     * @var array<int, array{
     *     type: string,
     *     base: array<string, mixed>,
     *     text?: string,
     *     thinking?: string,
     *     signature?: ?string,
     *     toolJsonBuffer?: string,
     * }>
     */
    private array $blocks = [];

    /** @var array<array-key, mixed> */
    private array $rawMessage = [];

    public function __construct()
    {
        $this->usage = new Usage(0, 0);
    }

    public function observe(StreamEvent $event): void
    {
        match (true) {
            $event instanceof MessageStartEvent => $this->onMessageStart($event),
            $event instanceof ContentBlockStartEvent => $this->onContentBlockStart($event),
            $event instanceof ContentBlockDeltaEvent => $this->onContentBlockDelta($event),
            $event instanceof MessageDeltaEvent => $this->onMessageDelta($event),
            $event instanceof ErrorEvent => throw new StreamException(sprintf(
                'In-stream error [%s]: %s',
                $event->errorType,
                $event->message,
            )),
            default => null,
        };
    }

    private function onMessageStart(MessageStartEvent $event): void
    {
        $msg = $event->message;
        $this->rawMessage = $msg;

        $this->id = isset($msg['id']) && is_string($msg['id']) ? $msg['id'] : '';
        $this->model = isset($msg['model']) && is_string($msg['model']) ? $msg['model'] : '';
        $roleRaw = isset($msg['role']) && is_string($msg['role']) ? $msg['role'] : Role::ASSISTANT->value;
        $this->role = Role::tryFrom($roleRaw) ?? Role::ASSISTANT;

        if (isset($msg['usage']) && is_array($msg['usage'])) {
            $this->usage = Usage::fromArray($msg['usage']);
        }
    }

    private function onContentBlockStart(ContentBlockStartEvent $event): void
    {
        $base = $event->contentBlock->toArray();
        $this->blocks[$event->index] = [
            'type' => $event->contentBlock->type(),
            'base' => $base,
            'text' => '',
            'thinking' => '',
            'signature' => null,
            'toolJsonBuffer' => '',
        ];
    }

    private function onContentBlockDelta(ContentBlockDeltaEvent $event): void
    {
        $idx = $event->index;
        if (!isset($this->blocks[$idx])) {
            // Defensive: a delta arrived before its start — synthesize an empty entry.
            $this->blocks[$idx] = [
                'type' => 'text',
                'base' => ['type' => 'text', 'text' => ''],
                'text' => '',
                'thinking' => '',
                'signature' => null,
                'toolJsonBuffer' => '',
            ];
        }

        $delta = $event->delta;
        if ($delta instanceof TextDelta) {
            $this->blocks[$idx]['text'] = ($this->blocks[$idx]['text'] ?? '') . $delta->text;
        } elseif ($delta instanceof ThinkingDelta) {
            $this->blocks[$idx]['thinking'] = ($this->blocks[$idx]['thinking'] ?? '') . $delta->thinking;
        } elseif ($delta instanceof SignatureDelta) {
            $this->blocks[$idx]['signature'] = $delta->signature;
        } elseif ($delta instanceof InputJsonDelta) {
            $this->blocks[$idx]['toolJsonBuffer'] = ($this->blocks[$idx]['toolJsonBuffer'] ?? '') . $delta->partialJson;
        }
    }

    private function onMessageDelta(MessageDeltaEvent $event): void
    {
        if ($event->stopReason !== null) {
            $this->stopReason = $event->stopReason;
        }
        if ($event->stopSequence !== null) {
            $this->stopSequence = $event->stopSequence;
        }
        if ($event->usage !== null) {
            // Output usage on message_delta is cumulative.
            $this->usage = new Usage(
                inputTokens: $this->usage->inputTokens,
                outputTokens: $event->usage->outputTokens,
                cacheCreationInputTokens: $event->usage->cacheCreationInputTokens ?? $this->usage->cacheCreationInputTokens,
                cacheReadInputTokens: $event->usage->cacheReadInputTokens ?? $this->usage->cacheReadInputTokens,
                serviceTier: $event->usage->serviceTier ?? $this->usage->serviceTier,
            );
        }
    }

    public function toMessage(): MessageResponse
    {
        ksort($this->blocks);

        $finalBlocks = [];
        $rawContent = [];
        foreach ($this->blocks as $idx => $entry) {
            $finalBlocks[] = $this->finalizeBlock($entry);
            $rawContent[$idx] = $finalBlocks[count($finalBlocks) - 1]->toArray();
        }

        $raw = $this->rawMessage;
        $raw['content'] = array_values($rawContent);
        if ($this->stopReason !== null) {
            $raw['stop_reason'] = $this->stopReason->value;
        }
        if ($this->stopSequence !== null) {
            $raw['stop_sequence'] = $this->stopSequence;
        }
        $raw['usage'] = [
            'input_tokens' => $this->usage->inputTokens,
            'output_tokens' => $this->usage->outputTokens,
            'cache_creation_input_tokens' => $this->usage->cacheCreationInputTokens,
            'cache_read_input_tokens' => $this->usage->cacheReadInputTokens,
            'service_tier' => $this->usage->serviceTier,
        ];

        return new MessageResponse(
            id: $this->id,
            role: $this->role,
            content: $finalBlocks,
            model: $this->model,
            stopReason: $this->stopReason,
            stopSequence: $this->stopSequence,
            usage: $this->usage,
            raw: $raw,
        );
    }

    /**
     * @param  array{
     *     type: string,
     *     base: array<string, mixed>,
     *     text?: string,
     *     thinking?: string,
     *     signature?: ?string,
     *     toolJsonBuffer?: string,
     * }  $entry
     */
    private function finalizeBlock(array $entry): ContentBlock
    {
        switch ($entry['type']) {
            case 'text':
                return new TextBlock($entry['text'] ?? '');

            case 'thinking':
                return new ThinkingBlock(
                    thinking: $entry['thinking'] ?? '',
                    signature: $entry['signature'] ?? null,
                );

            case 'tool_use':
                $base = $entry['base'];
                $jsonBuffer = $entry['toolJsonBuffer'] ?? '';
                $input = [];
                if ($jsonBuffer !== '') {
                    try {
                        /** @var array<string, mixed> $decoded */
                        $decoded = Json::decode($jsonBuffer);
                        $input = $decoded;
                    } catch (\Throwable) {
                        // Leave input empty if the buffered JSON is malformed.
                    }
                } elseif (isset($base['input']) && is_array($base['input'])) {
                    /** @var array<string, mixed> $bi */
                    $bi = $base['input'];
                    $input = $bi;
                }

                return new ToolUseBlock(
                    id: isset($base['id']) && is_string($base['id']) ? $base['id'] : '',
                    name: isset($base['name']) && is_string($base['name']) ? $base['name'] : '',
                    input: $input,
                );

            default:
                /** @var array<string, mixed> $base */
                $base = $entry['base'];

                return ContentBlockFactory::fromArray($base);
        }
    }
}
