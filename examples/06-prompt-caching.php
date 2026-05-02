<?php

declare(strict_types=1);

/**
 * 06 — Prompt caching.
 *
 * Sends the same long system prompt twice. The second call should report
 * non-zero cacheReadInputTokens, demonstrating the cache hit.
 *
 * Note: prompt caching has a 1024-token minimum for the cached block.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Messages\Content\CacheControl;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Models\Model;

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY') ?: '');

// Build a long-enough system prompt for caching to engage.
$longContext = str_repeat(
    "You are a precise editor. Always reply with concise corrections. ",
    300,
);

$system = [
    TextBlock::of('You are a helpful assistant.'),
    TextBlock::of($longContext)->withCacheControl(CacheControl::ephemeral('1h')),
];

foreach ([1, 2] as $callNumber) {
    $response = $client->messages()->create(
        model: Model::CLAUDE_SONNET_4_7,
        maxTokens: 256,
        system: $system,
        messages: [['role' => 'user', 'content' => 'Reply with the single word "ok".']],
    );

    printf(
        "Call %d  input=%d  cache_creation=%s  cache_read=%s  output=%d\n",
        $callNumber,
        $response->usage->inputTokens,
        var_export($response->usage->cacheCreationInputTokens, true),
        var_export($response->usage->cacheReadInputTokens, true),
        $response->usage->outputTokens,
    );
}
