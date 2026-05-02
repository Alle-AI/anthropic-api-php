<?php

declare(strict_types=1);

/**
 * 07 — Extended thinking.
 *
 * Asks a reasoning-enabled Claude model to solve a problem with a thinking
 * budget. Prints the thinking blocks (preview) then the final answer.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Messages\Content\ThinkingBlock;
use AlleAI\Anthropic\Messages\ThinkingConfig;
use AlleAI\Anthropic\Models\Model;

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY') ?: '');

$response = $client->messages()->create(
    model: Model::CLAUDE_OPUS_4_7,
    maxTokens: 8000,
    thinking: ThinkingConfig::enabled(budgetTokens: 4000),
    messages: [[
        'role' => 'user',
        'content' => 'A bat and a ball cost $1.10. The bat costs one dollar more than the ball. How much does the ball cost? Show your work briefly.',
    ]],
);

foreach ($response->content as $block) {
    if ($block instanceof ThinkingBlock) {
        echo '[thinking] ', substr($block->thinking, 0, 200), "...\n\n";
    }
    if ($block instanceof TextBlock) {
        echo $block->text, "\n";
    }
}
