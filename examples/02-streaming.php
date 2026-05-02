<?php

declare(strict_types=1);

/**
 * 02 — Streaming.
 *
 * Streams Claude's response token-by-token. Then aggregates the stream
 * into a final MessageResponse and prints usage.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Streaming\Events\ContentBlockDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\Deltas\TextDelta;

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY') ?: '');

$stream = $client->messages()->stream(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 512,
    messages: [
        ['role' => 'user', 'content' => 'Tell me a 4-line story about a curious robot.'],
    ],
);

foreach ($stream as $event) {
    if ($event instanceof ContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
        echo $event->delta->text;
        flush();
    }
}

$final = $stream->toMessage();
echo "\n\n---\n";
printf(
    "stop_reason=%s  output_tokens=%d\n",
    $final->stopReason?->value ?? 'unknown',
    $final->usage->outputTokens,
);
