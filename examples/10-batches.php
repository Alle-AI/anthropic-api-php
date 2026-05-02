<?php

declare(strict_types=1);

/**
 * 10 — Message Batches.
 *
 * Submits two requests in a batch, polls until done, then streams the
 * JSONL results line by line. Anthropic charges 50% of normal cost for
 * batch requests.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Batches\BatchEntry;
use AlleAI\Anthropic\Beta\BetaHeaders;
use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Models\Model;

$client = Client::builder()
    ->withApiKey(getenv('ANTHROPIC_API_KEY') ?: '')
    ->withAnthropicBeta(BetaHeaders::MESSAGE_BATCHES)
    ->build();

$batch = $client->batches()->create([
    new BatchEntry('row-1', [
        'model' => Model::CLAUDE_HAIKU_4_5,
        'max_tokens' => 64,
        'messages' => [['role' => 'user', 'content' => 'Classify this review as positive/negative: "I love this product!"']],
    ]),
    new BatchEntry('row-2', [
        'model' => Model::CLAUDE_HAIKU_4_5,
        'max_tokens' => 64,
        'messages' => [['role' => 'user', 'content' => 'Classify this review as positive/negative: "Worst purchase ever."']],
    ]),
]);

printf("Submitted batch %s (status=%s)\n", $batch->id, $batch->processingStatus->value);

$done = $client->batches()->pollUntilDone($batch->id, intervalSeconds: 5.0, timeoutSeconds: 600.0);
printf("Batch %s finished\n\n", $done->id);

foreach ($client->batches()->results($done->id) as $result) {
    if ($result->succeeded() && $result->message !== null) {
        printf("[%s] %s\n", $result->customId, $result->message->text());
    } else {
        printf("[%s] ERROR (%s)\n", $result->customId, $result->resultType);
    }
}
