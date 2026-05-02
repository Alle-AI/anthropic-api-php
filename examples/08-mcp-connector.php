<?php

declare(strict_types=1);

/**
 * 08 — MCP connector (beta).
 *
 * Lets Claude call tools on a remote MCP server via Anthropic's hosted
 * connector. The MCP wire format is still in beta — pass the
 * BetaHeaders::MCP_CLIENT flag explicitly.
 *
 * Replace the example URL/token with your own MCP server before running.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Beta\BetaHeaders;
use AlleAI\Anthropic\Beta\Mcp\McpServer;
use AlleAI\Anthropic\Beta\Mcp\McpToolApproval;
use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Messages\Content\McpToolResultBlock;
use AlleAI\Anthropic\Messages\Content\McpToolUseBlock;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Models\Model;

$client = Client::builder()
    ->withApiKey(getenv('ANTHROPIC_API_KEY') ?: '')
    ->withAnthropicBeta(BetaHeaders::MCP_CLIENT)
    ->build();

$mcpServer = McpServer::url(
    name: 'demo',
    url: getenv('MCP_SERVER_URL') ?: 'https://example.com/mcp',
    authorizationToken: getenv('MCP_TOKEN') ?: null,
    toolApproval: McpToolApproval::never(),
);

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 4096,
    messages: [['role' => 'user', 'content' => 'Use any tools available on the demo server to answer: what are the most recent items?']],
    mcpServers: [$mcpServer->toArray()],
);

foreach ($response->content as $block) {
    if ($block instanceof McpToolUseBlock) {
        printf("[mcp call] %s.%s(%s)\n", $block->serverName, $block->name, json_encode($block->input));
    }
    if ($block instanceof McpToolResultBlock) {
        printf("[mcp result] %s%s\n", $block->isError ? '(error) ' : '', is_string($block->content) ? $block->content : json_encode($block->content));
    }
    if ($block instanceof TextBlock) {
        echo $block->text, "\n";
    }
}
