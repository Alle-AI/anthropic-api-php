<?php

declare(strict_types=1);

/**
 * 09 — Files API (beta).
 *
 * Uploads a PDF and asks Claude to summarize it. Provide a path as the
 * first argument; otherwise a one-line "hello.pdf" stand-in is generated.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Beta\BetaHeaders;
use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Files\FileUpload;
use AlleAI\Anthropic\Messages\Content\DocumentBlock;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Models\Model;

$client = Client::builder()
    ->withApiKey(getenv('ANTHROPIC_API_KEY') ?: '')
    ->withAnthropicBeta(BetaHeaders::FILES_API)
    ->build();

$path = $argv[1] ?? null;
$upload = $path !== null
    ? FileUpload::fromPath($path)
    : FileUpload::fromString('hello.txt', "Hello from the Anthropic PHP SDK.\n", 'text/plain');

$file = $client->files()->upload($upload);
printf("Uploaded %s (%s, %d bytes) -> %s\n", $file->filename, $file->mimeType, $file->sizeBytes, $file->id);

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    messages: [[
        'role' => 'user',
        'content' => [
            DocumentBlock::fromFileId($file->id),
            TextBlock::of('Summarize this document in two sentences.'),
        ],
    ]],
);

echo $response->text(), "\n";
