<?php

declare(strict_types=1);

/**
 * 14 — Concurrent fan-out.
 *
 * Sends N independent Messages.create() calls in parallel via libcurl
 * multi-handle. Failures land in-place as exceptions rather than aborting
 * the batch.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Exceptions\AnthropicException;
use AlleAI\Anthropic\Messages\MessageResponse;
use AlleAI\Anthropic\Models\Model;

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY') ?: '');

$prompts = [
    'Translate "hello" to French.',
    'Translate "hello" to Spanish.',
    'Translate "hello" to Japanese.',
    'Translate "hello" to Swahili.',
    'Translate "hello" to Akan.',
];

$requests = array_map(static fn (string $prompt): array => [
    'model' => Model::CLAUDE_HAIKU_4_5,
    'maxTokens' => 64,
    'messages' => [['role' => 'user', 'content' => $prompt]],
], $prompts);

$started = microtime(true);
$results = $client->messages()->createMany($requests, concurrency: 5);
$elapsed = microtime(true) - $started;

foreach ($results as $i => $result) {
    if ($result instanceof MessageResponse) {
        printf("[%d] %s\n", $i, trim($result->text()));
    } elseif ($result instanceof AnthropicException) {
        printf("[%d] ERROR: %s\n", $i, $result->getMessage());
    }
}

printf("\nFan-out of %d requests in %.2fs\n", count($prompts), $elapsed);
