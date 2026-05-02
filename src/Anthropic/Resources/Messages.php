<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Resources;

use AlleAI\Anthropic\ClientOptions;
use AlleAI\Anthropic\Http\CurlStreamTransport;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\Transport;
use AlleAI\Anthropic\Messages\Content\ContentBlock;
use AlleAI\Anthropic\Messages\MessageResponse;
use AlleAI\Anthropic\Messages\ThinkingConfig;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Streaming\EventStream;
use AlleAI\Anthropic\Tools\ToolChoice;
use AlleAI\Anthropic\Tools\ToolLoop;
use AlleAI\Anthropic\Tools\ToolSet;
use AlleAI\Anthropic\Util\Json;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Resource for the `/v1/messages` endpoint — Claude's primary chat surface.
 *
 * ```php
 * $response = $client->messages()->create(
 *     model: Model::CLAUDE_SONNET_4_7,
 *     maxTokens: 1024,
 *     messages: [['role' => 'user', 'content' => 'Hello, Claude!']],
 * );
 * echo $response->text();
 * ```
 */
final class Messages
{
    public function __construct(
        private readonly Transport $transport,
        private readonly CurlStreamTransport $streamTransport,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ClientOptions $options,
    ) {
    }

    /**
     * Create a message.
     *
     * @param  Model|string  $model
     * @param  list<array<string, mixed>>  $messages
     * @param  string|list<ContentBlock|array<string, mixed>>|null  $system
     * @param  list<string>|null  $stopSequences
     * @param  list<array<string, mixed>>|null  $tools
     * @param  array<string, mixed>|string|null  $toolChoice
     * @param  array<string, string>|null  $metadata
     * @param  list<array<string, mixed>>|null  $mcpServers
     * @param  array<string, string>  $extraHeaders
     * @param  array<string, mixed>  $extraBody
     */
    public function create(
        Model|string $model,
        int $maxTokens,
        array $messages,
        string|array|null $system = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?int $topK = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        array|string|null $toolChoice = null,
        ?array $metadata = null,
        ?ThinkingConfig $thinking = null,
        ?array $mcpServers = null,
        ?string $idempotencyKey = null,
        array $extraHeaders = [],
        array $extraBody = [],
    ): MessageResponse {
        $body = $this->buildBody(
            model: $model,
            maxTokens: $maxTokens,
            messages: $messages,
            system: $system,
            temperature: $temperature,
            topP: $topP,
            topK: $topK,
            stopSequences: $stopSequences,
            tools: $tools,
            toolChoice: $toolChoice,
            metadata: $metadata,
            thinking: $thinking,
            mcpServers: $mcpServers,
            stream: false,
            extraBody: $extraBody,
        );

        $request = $this->buildRequest($body, $idempotencyKey, $extraHeaders);
        $response = $this->transport->sendRequest($request);
        $decoded = Json::decode((string) $response->getBody());

        return MessageResponse::fromArray($decoded);
    }

    /**
     * Stream a message via SSE.
     *
     * Returns an iterable of typed {@see \AlleAI\Anthropic\Streaming\Events\StreamEvent}.
     * Call `->toMessage()` on the returned stream to get an aggregated
     * {@see MessageResponse} once all events have been observed.
     *
     * @param  Model|string  $model
     * @param  list<array<string, mixed>>  $messages
     * @param  string|list<ContentBlock|array<string, mixed>>|null  $system
     * @param  list<string>|null  $stopSequences
     * @param  list<array<string, mixed>>|null  $tools
     * @param  array<string, mixed>|string|null  $toolChoice
     * @param  array<string, string>|null  $metadata
     * @param  list<array<string, mixed>>|null  $mcpServers
     * @param  array<string, string>  $extraHeaders
     * @param  array<string, mixed>  $extraBody
     */
    public function stream(
        Model|string $model,
        int $maxTokens,
        array $messages,
        string|array|null $system = null,
        ?float $temperature = null,
        ?float $topP = null,
        ?int $topK = null,
        ?array $stopSequences = null,
        ?array $tools = null,
        array|string|null $toolChoice = null,
        ?array $metadata = null,
        ?ThinkingConfig $thinking = null,
        ?array $mcpServers = null,
        ?string $idempotencyKey = null,
        array $extraHeaders = [],
        array $extraBody = [],
    ): EventStream {
        $body = $this->buildBody(
            model: $model,
            maxTokens: $maxTokens,
            messages: $messages,
            system: $system,
            temperature: $temperature,
            topP: $topP,
            topK: $topK,
            stopSequences: $stopSequences,
            tools: $tools,
            toolChoice: $toolChoice,
            metadata: $metadata,
            thinking: $thinking,
            mcpServers: $mcpServers,
            stream: true,
            extraBody: $extraBody,
        );

        $request = $this->buildRequest($body, $idempotencyKey, $extraHeaders)
            ->withHeader(Headers::ACCEPT, 'text/event-stream');

        return new EventStream($this->streamTransport->stream($request));
    }

    /**
     * Convenience factory for an automatic tool-use loop.
     *
     * @param  Model|string  $model
     * @param  list<array<string, mixed>>  $messages
     * @param  string|list<ContentBlock|array<string, mixed>>|null  $system
     */
    public function toolLoop(
        Model|string $model,
        int $maxTokens,
        array $messages,
        ToolSet $tools,
        string|array|null $system = null,
        ?ToolChoice $toolChoice = null,
        ?\AlleAI\Anthropic\Messages\ThinkingConfig $thinking = null,
        int $maxIterations = 10,
        bool $catchToolErrors = true,
    ): ToolLoop {
        return new ToolLoop(
            messages: $this,
            model: $model,
            maxTokens: $maxTokens,
            initialMessages: $messages,
            tools: $tools,
            system: $system,
            toolChoice: $toolChoice,
            thinking: $thinking,
            maxIterations: $maxIterations,
            catchToolErrors: $catchToolErrors,
        );
    }

    /**
     * Count tokens for a hypothetical request without actually creating a message.
     *
     * @param  Model|string  $model
     * @param  list<array<string, mixed>>  $messages
     * @param  string|list<ContentBlock|array<string, mixed>>|null  $system
     * @param  list<array<string, mixed>>|null  $tools
     * @param  array<string, string>  $extraHeaders
     *
     * @return array{input_tokens: int}
     */
    public function countTokens(
        Model|string $model,
        array $messages,
        string|array|null $system = null,
        ?array $tools = null,
        array $extraHeaders = [],
    ): array {
        $body = [
            'model' => (string) $model,
            'messages' => $this->normalizeMessages($messages),
        ];

        if ($system !== null) {
            $body['system'] = $this->normalizeSystem($system);
        }
        if ($tools !== null) {
            $body['tools'] = $tools;
        }

        $request = $this->requestFactory->createRequest('POST', $this->endpoint('/v1/messages/count_tokens'));
        foreach ($this->defaultHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        foreach ($extraHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $request = $request->withBody($this->streamFactory->createStream(Json::encode($body)));

        $response = $this->transport->sendRequest($request);
        $decoded = Json::decode((string) $response->getBody());

        $tokens = $decoded['input_tokens'] ?? 0;

        return [
            'input_tokens' => is_numeric($tokens) ? (int) $tokens : 0,
        ];
    }

    /**
     * @param  Model|string  $model
     * @param  list<array<string, mixed>>  $messages
     * @param  string|list<ContentBlock|array<string, mixed>>|null  $system
     * @param  list<string>|null  $stopSequences
     * @param  list<array<string, mixed>>|null  $tools
     * @param  array<string, mixed>|string|null  $toolChoice
     * @param  array<string, string>|null  $metadata
     * @param  list<array<string, mixed>>|null  $mcpServers
     * @param  array<string, mixed>  $extraBody
     *
     * @return array<string, mixed>
     */
    private function buildBody(
        Model|string $model,
        int $maxTokens,
        array $messages,
        string|array|null $system,
        ?float $temperature,
        ?float $topP,
        ?int $topK,
        ?array $stopSequences,
        ?array $tools,
        array|string|null $toolChoice,
        ?array $metadata,
        ?ThinkingConfig $thinking,
        ?array $mcpServers,
        bool $stream,
        array $extraBody,
    ): array {
        $body = [
            'model' => (string) $model,
            'max_tokens' => $maxTokens,
            'messages' => $this->normalizeMessages($messages),
        ];

        if ($system !== null) {
            $body['system'] = $this->normalizeSystem($system);
        }
        if ($temperature !== null) {
            $body['temperature'] = $temperature;
        }
        if ($topP !== null) {
            $body['top_p'] = $topP;
        }
        if ($topK !== null) {
            $body['top_k'] = $topK;
        }
        if ($stopSequences !== null && $stopSequences !== []) {
            $body['stop_sequences'] = $stopSequences;
        }
        if ($tools !== null && $tools !== []) {
            $body['tools'] = $tools;
        }
        if ($toolChoice !== null) {
            $body['tool_choice'] = is_string($toolChoice) ? ['type' => $toolChoice] : $toolChoice;
        }
        if ($metadata !== null && $metadata !== []) {
            $body['metadata'] = $metadata;
        }
        if ($thinking !== null) {
            $body['thinking'] = $thinking->toArray();
        }
        if ($mcpServers !== null && $mcpServers !== []) {
            $body['mcp_servers'] = $mcpServers;
        }
        if ($stream) {
            $body['stream'] = true;
        }

        return array_replace($body, $extraBody);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $extraHeaders
     */
    private function buildRequest(
        array $body,
        ?string $idempotencyKey,
        array $extraHeaders,
    ): \Psr\Http\Message\RequestInterface {
        $request = $this->requestFactory->createRequest('POST', $this->endpoint('/v1/messages'));

        foreach ($this->defaultHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($idempotencyKey !== null) {
            $request = $request->withHeader(Headers::IDEMPOTENCY_KEY, $idempotencyKey);
        }

        foreach ($extraHeaders as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request->withBody($this->streamFactory->createStream(Json::encode($body)));
    }

    /**
     * @return array<string, string>
     */
    private function defaultHeaders(): array
    {
        $headers = [
            Headers::CONTENT_TYPE => 'application/json',
            Headers::ACCEPT => 'application/json',
            Headers::ANTHROPIC_VERSION => $this->options->anthropicVersion,
        ];

        if ($this->options->anthropicBeta !== []) {
            $headers[Headers::ANTHROPIC_BETA] = implode(',', $this->options->anthropicBeta);
        }

        return $headers;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->options->baseUrl, '/') . $path;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeMessages(array $messages): array
    {
        return array_values(array_map(
            function (array $message): array {
                $content = $message['content'] ?? '';
                $message['content'] = $this->normalizeContent($content);

                return $message;
            },
            $messages,
        ));
    }

    /**
     * @param  string|list<ContentBlock|array<string, mixed>>  $system
     *
     * @return string|list<array<string, mixed>>
     */
    private function normalizeSystem(string|array $system): string|array
    {
        if (is_string($system)) {
            return $system;
        }

        return array_values(array_map(
            static fn (ContentBlock|array $item): array => $item instanceof ContentBlock ? $item->toArray() : $item,
            $system,
        ));
    }

    /**
     * @return string|list<array<string, mixed>>
     */
    private function normalizeContent(mixed $content): string|array
    {
        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content)) {
            throw new \InvalidArgumentException(
                'Message content must be a string or a list of content blocks/arrays.',
            );
        }

        $out = [];
        foreach ($content as $item) {
            if ($item instanceof ContentBlock) {
                $out[] = $item->toArray();
                continue;
            }
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $out[] = $item;
                continue;
            }
            if (is_string($item)) {
                $out[] = ['type' => 'text', 'text' => $item];
                continue;
            }

            throw new \InvalidArgumentException(
                'Each content item must be a ContentBlock, array, or string.',
            );
        }

        return $out;
    }
}
