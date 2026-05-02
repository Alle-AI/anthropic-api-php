<?php

declare(strict_types=1);

/**
 * 05 — Vision (image input).
 *
 * Asks Claude to describe a local image file. Provide your own path or
 * URL — the example uses a placeholder URL by default.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Messages\Content\ImageBlock;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use AlleAI\Anthropic\Models\Model;

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY') ?: '');

$imagePath = $argv[1] ?? null;

$image = $imagePath !== null && is_readable($imagePath)
    ? ImageBlock::fromFile($imagePath)
    : ImageBlock::fromUrl('https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/PNG_transparency_demonstration_1.png/280px-PNG_transparency_demonstration_1.png');

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 512,
    messages: [[
        'role' => 'user',
        'content' => [
            $image,
            TextBlock::of('Describe this image in two sentences.'),
        ],
    ]],
);

echo $response->text(), "\n";
