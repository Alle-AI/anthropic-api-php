<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools;

use AlleAI\Anthropic\Messages\Content\ContentBlock;
use AlleAI\Anthropic\Messages\Content\ToolResultBlock;
use AlleAI\Anthropic\Messages\Content\ToolUseBlock;
use AlleAI\Anthropic\Messages\MessageResponse;
use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Messages\ThinkingConfig;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Resources\Messages;

/**
 * Runs an automatic tool-use loop: ask Claude → execute any tool calls →
 * append tool_result → ask again, until Claude returns end_turn (or any
 * non-tool stop reason) or `maxIterations` is hit.
 *
 * ```php
 * $loop = new ToolLoop(
 *     messages: $client->messages(),
 *     model: Model::CLAUDE_SONNET_4_7,
 *     maxTokens: 4096,
 *     initialMessages: [['role' => 'user', 'content' => 'Weather in Accra?']],
 *     tools: new ToolSet(new GetWeather()),
 * );
 * $final = $loop->run();
 * echo $final->text();
 * ```
 */
final class ToolLoop
{
    /** @var list<array<string, mixed>> */
    private array $conversation;

    /** @var list<MessageResponse> */
    private array $trace = [];

    /**
     * @param  Model|string  $model
     * @param  list<array<string, mixed>>  $initialMessages
     * @param  string|list<ContentBlock|array<string, mixed>>|null  $system
     */
    public function __construct(
        private readonly Messages $messages,
        private readonly Model|string $model,
        private readonly int $maxTokens,
        array $initialMessages,
        private readonly ToolSet $tools,
        private readonly string|array|null $system = null,
        private readonly ?ToolChoice $toolChoice = null,
        private readonly ?ThinkingConfig $thinking = null,
        private readonly int $maxIterations = 10,
        private readonly bool $catchToolErrors = true,
    ) {
        $this->conversation = $initialMessages;
    }

    /**
     * Run until Claude returns a non-tool stop reason or `maxIterations`.
     */
    public function run(): MessageResponse
    {
        if ($this->tools->isEmpty()) {
            throw new \LogicException('ToolLoop requires at least one tool. Use Messages::create() for tool-less calls.');
        }

        $iterations = 0;
        $last = null;

        while ($iterations < $this->maxIterations) {
            $iterations++;

            $response = $this->messages->create(
                model: $this->model,
                maxTokens: $this->maxTokens,
                messages: $this->conversation,
                system: $this->system,
                tools: $this->tools->toArray(),
                toolChoice: $this->toolChoice?->toArray(),
                thinking: $this->thinking,
            );

            $this->trace[] = $response;
            $last = $response;

            if ($response->stopReason !== StopReason::TOOL_USE) {
                return $response;
            }

            // Append the assistant message verbatim, then the tool_result blocks.
            $this->conversation[] = [
                'role' => 'assistant',
                'content' => array_map(
                    static fn (ContentBlock $b): array => $b->toArray(),
                    $response->content,
                ),
            ];

            $resultBlocks = [];
            foreach ($response->toolUses() as $toolUse) {
                $resultBlocks[] = $this->execute($toolUse);
            }

            $this->conversation[] = [
                'role' => 'user',
                'content' => array_map(
                    static fn (ToolResultBlock $b): array => $b->toArray(),
                    $resultBlocks,
                ),
            ];
        }

        if ($last === null) {
            throw new \LogicException('ToolLoop did not produce any response.');
        }

        return $last;
    }

    /**
     * Snapshot of every assistant response observed during the loop.
     *
     * @return list<MessageResponse>
     */
    public function trace(): array
    {
        return $this->trace;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function conversation(): array
    {
        return $this->conversation;
    }

    private function execute(ToolUseBlock $call): ToolResultBlock
    {
        if (!$this->tools->has($call->name)) {
            return ToolResultBlock::error(
                $call->id,
                sprintf('Tool "%s" is not registered with this loop.', $call->name),
            );
        }

        $tool = $this->tools->get($call->name);

        try {
            $result = $tool->run($call->input);
        } catch (\Throwable $e) {
            if (!$this->catchToolErrors) {
                throw $e;
            }

            return ToolResultBlock::error($call->id, sprintf(
                '%s: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        return ToolResultBlock::ok($call->id, $this->normalizeResult($result));
    }

    /**
     * Coerce arbitrary tool return values into something json_encode can
     * round-trip in a tool_result block.
     */
    private function normalizeResult(mixed $result): mixed
    {
        if ($result instanceof \JsonSerializable) {
            return $result;
        }

        if ($result instanceof \Stringable) {
            return (string) $result;
        }

        if (is_object($result) && !($result instanceof ContentBlock)) {
            return get_object_vars($result);
        }

        return $result;
    }
}
