<?php

declare(strict_types=1);

/**
 * Quickstart example.
 *
 * For more examples, see the examples/ directory.
 *
 * Usage:
 *   composer install
 *   composer require guzzlehttp/guzzle nyholm/psr7   # if not already installed
 *   ANTHROPIC_API_KEY=sk-ant-... php example.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Models\Model;

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY') ?: 'your-anthropic-api-key');

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 300,
    messages: [
        ['role' => 'user', 'content' => 'How many toes do dogs have?'],
    ],
);

echo $response->text(), "\n";
