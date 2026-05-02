<?php

declare(strict_types=1);

/**
 * 01 — Simple message.
 *
 * Sends a single user message and prints the assistant's reply.
 *
 * Usage:
 *   ANTHROPIC_API_KEY=sk-ant-... php examples/01-simple-message.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Exceptions\AnthropicException;
use AlleAI\Anthropic\Models\Model;

$apiKey = getenv('ANTHROPIC_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "Set ANTHROPIC_API_KEY in your environment.\n");
    exit(1);
}

$client = Client::fromApiKey($apiKey);

try {
    $response = $client->messages()->create(
        model: Model::CLAUDE_SONNET_4_7,
        maxTokens: 256,
        messages: [
            ['role' => 'user', 'content' => 'Write a haiku about static typing.'],
        ],
    );
} catch (AnthropicException $e) {
    fwrite(STDERR, sprintf("API call failed: %s\n", $e->getMessage()));
    exit(1);
}

echo $response->text(), "\n\n";

printf(
    "stop_reason=%s  input=%d  output=%d  total=%d\n",
    $response->stopReason?->value ?? 'unknown',
    $response->usage->inputTokens,
    $response->usage->outputTokens,
    $response->usage->totalTokens(),
);
