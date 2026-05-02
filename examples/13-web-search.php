<?php

declare(strict_types=1);

/**
 * 13 — Web search (server-side tool).
 *
 * Asks Claude to search the web. Anthropic runs the search server-side
 * and returns server_tool_use + web_search_tool_result blocks alongside
 * the model's text answer.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Messages\Content\ServerToolUseBlock;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\WebSearchToolResultBlock;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Tools\Server\WebSearchToolDefinition;

$client = Client::builder()
    ->withApiKey(getenv('ANTHROPIC_API_KEY') ?: '')
    ->withAnthropicBeta(WebSearchToolDefinition::BETA_HEADER)
    ->build();

$tool = WebSearchToolDefinition::create(maxUses: 3);

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    messages: [['role' => 'user', 'content' => 'What were the top three AI stories on Hacker News this week? Cite your sources.']],
    tools: [$tool->toArray()],
);

foreach ($response->content as $block) {
    if ($block instanceof ServerToolUseBlock) {
        printf("[search] %s(%s)\n", $block->name, json_encode($block->input));
    }
    if ($block instanceof WebSearchToolResultBlock) {
        if ($block->isError()) {
            printf("[search error] %s\n", $block->errorCode());
        } else {
            foreach ($block->results() as $r) {
                printf("[result] %s — %s\n", $r['title'] ?? '?', $r['url'] ?? '?');
            }
        }
    }
    if ($block instanceof TextBlock) {
        echo $block->text, "\n";
    }
}
