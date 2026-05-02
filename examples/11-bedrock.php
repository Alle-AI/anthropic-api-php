<?php

declare(strict_types=1);

/**
 * 11 — AWS Bedrock.
 *
 * Same client surface, different backend. Requires aws/aws-sdk-php and
 * AWS credentials reachable by the default chain (env vars, ~/.aws/credentials,
 * IAM role, etc.).
 *
 *   composer require aws/aws-sdk-php
 *   AWS_REGION=us-east-1 php examples/11-bedrock.php
 *
 * Bedrock model ids look like `anthropic.claude-sonnet-4-7-v1:0`, NOT
 * the `claude-sonnet-4-7` aliases used on the direct API.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Auth\BedrockAuth;
use AlleAI\Anthropic\Client;

$client = Client::builder()
    ->withAuth(BedrockAuth::fromEnvironment(region: getenv('AWS_REGION') ?: 'us-east-1'))
    ->build();

$response = $client->messages()->create(
    model: 'anthropic.claude-sonnet-4-7-v1:0',
    maxTokens: 256,
    messages: [['role' => 'user', 'content' => 'Hello from Bedrock!']],
);

echo $response->text(), "\n";
