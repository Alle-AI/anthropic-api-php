<?php

declare(strict_types=1);

/**
 * 12 — Google Cloud Vertex AI.
 *
 * Same client surface, Vertex backend. Requires google/auth and Application
 * Default Credentials configured (gcloud auth application-default login,
 * service account key, or GCE/GKE metadata server).
 *
 *   composer require google/auth
 *   GOOGLE_CLOUD_PROJECT=my-project GOOGLE_CLOUD_REGION=us-east5 php examples/12-vertex.php
 *
 * Vertex model ids use the `claude-sonnet-4-7@YYYYMMDD` publisher format.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AlleAI\Anthropic\Auth\VertexAuth;
use AlleAI\Anthropic\Client;

$client = Client::builder()
    ->withAuth(VertexAuth::fromEnvironment(
        projectId: getenv('GOOGLE_CLOUD_PROJECT') ?: null,
        region: getenv('GOOGLE_CLOUD_REGION') ?: 'us-east5',
    ))
    ->build();

$response = $client->messages()->create(
    model: getenv('VERTEX_MODEL') ?: 'claude-sonnet-4-7@20260101',
    maxTokens: 256,
    messages: [['role' => 'user', 'content' => 'Hello from Vertex AI!']],
);

echo $response->text(), "\n";
