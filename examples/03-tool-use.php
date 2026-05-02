<?php

declare(strict_types=1);

/**
 * 03 — Tool use (manual round-trip).
 *
 * Shows a single tool call cycle: Claude requests `get_weather`, we run it,
 * we send the tool_result back, Claude returns the final answer.
 *
 * For the auto-loop version see 04-tool-loop.php.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Messages\Content\ToolResultBlock;
use AlleAI\Anthropic\Messages\StopReason;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Tools\ClosureTool;

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY') ?: '');

$weatherTool = ClosureTool::create(
    name: 'get_weather',
    description: 'Get the current weather for a city.',
    schema: [
        'type' => 'object',
        'properties' => [
            'city' => ['type' => 'string', 'description' => 'City name'],
        ],
        'required' => ['city'],
    ],
    handler: static fn (array $input): array => [
        'city' => $input['city'] ?? 'unknown',
        'temp_c' => 24,
        'condition' => 'sunny',
    ],
);

$conversation = [
    ['role' => 'user', 'content' => "What's the weather in Accra right now?"],
];

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    messages: $conversation,
    tools: [$weatherTool->toArray()],
);

if ($response->stopReason === StopReason::TOOL_USE) {
    $conversation[] = [
        'role' => 'assistant',
        'content' => array_map(static fn ($b) => $b->toArray(), $response->content),
    ];

    $resultBlocks = [];
    foreach ($response->toolUses() as $call) {
        $output = $weatherTool->run($call->input);
        $resultBlocks[] = ToolResultBlock::ok($call->id, $output)->toArray();
    }

    $conversation[] = ['role' => 'user', 'content' => $resultBlocks];

    $response = $client->messages()->create(
        model: Model::CLAUDE_SONNET_4_7,
        maxTokens: 1024,
        messages: $conversation,
        tools: [$weatherTool->toArray()],
    );
}

echo $response->text(), "\n";
