# anthropic-api-php

> The go-to PHP library for the Anthropic API. Maintained by [Alle-AI](https://alle-ai.com).

[![Latest Version](https://img.shields.io/packagist/v/alle-ai/anthropic-api-php.svg?style=flat-square)](https://packagist.org/packages/alle-ai/anthropic-api-php)
[![Total Downloads](https://img.shields.io/packagist/dt/alle-ai/anthropic-api-php.svg?style=flat-square)](https://packagist.org/packages/alle-ai/anthropic-api-php)
[![PHP Version](https://img.shields.io/packagist/php-v/alle-ai/anthropic-api-php.svg?style=flat-square)](https://packagist.org/packages/alle-ai/anthropic-api-php)
[![License](https://img.shields.io/packagist/l/alle-ai/anthropic-api-php.svg?style=flat-square)](LICENSE)

A first-class PHP client for the Anthropic Messages API. Built for Claude 4 and beyond — Messages, streaming, tool use, vision, prompt caching, extended thinking, MCP connector, Files, Batches. Works with the direct API, **AWS Bedrock**, and **Google Vertex AI**.

> **Looking for the v1.x docs?** See the [`1.x`](https://github.com/Alle-AI/anthropic-api-php/tree/1.x) branch. The v1 surface (`Alle_AI\Anthropic\AnthropicAPI`) is preserved as a deprecation shim through the v2.x line and removed in v3.0. See [UPGRADING.md](UPGRADING.md).

## Features

- **Messages API** — typed requests and responses for `POST /v1/messages`
- **Streaming** — Generator-based SSE iterator with `->toMessage()` aggregator
- **Tool use** — closure tools, class-based tools with reflection-driven JSON Schema, automatic tool-loop helper
- **Vision** — `ImageBlock::fromFile()` / `fromUrl()` / `fromBase64()`
- **Prompt caching** — `cache_control` on any block; `Usage::$cacheReadInputTokens` in the response
- **Extended thinking** — `ThinkingConfig::enabled(budgetTokens: 10_000)` for reasoning models
- **MCP connector** — call any remote MCP server via Anthropic's hosted connector
- **Files API** — `Resources\Files::upload()` / `get()` / `list()` / `delete()` / `downloadTo()`
- **Batches API** — `Resources\Batches` with JSONL results streaming and a `pollUntilDone()` helper
- **Models listing** — `$client->models()->list()` paginated catalog
- **PSR-18 / PSR-17** — bring any HTTP client (Guzzle, Symfony HttpClient, Buzz, …)
- **Retries** — exponential backoff with jitter, honors `Retry-After`, idempotency keys auto-attached
- **PSR-3 logging** — opt-in `LoggingMiddleware` with correlation ids, latency, and request-id
- **Pluggable auth** — API key, Bearer token, **AWS Bedrock** (SigV4), **Google Vertex AI** (ADC + OAuth)

## Installation

Requires **PHP 8.2 or newer**.

```bash
composer require alle-ai/anthropic-api-php
```

You'll also need a PSR-18 HTTP client and PSR-17 factories. The most popular choice:

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

## Quick start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\Models\Model;

$client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY'));

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    messages: [
        ['role' => 'user', 'content' => 'Write a haiku about PHP.'],
    ],
);

echo $response->text();
echo "\n\nUsed {$response->usage->inputTokens} input + {$response->usage->outputTokens} output tokens.\n";
```

### From environment

```php
$client = Client::fromEnvironment(); // reads ANTHROPIC_API_KEY
```

### Builder for advanced configuration

```php
use AlleAI\Anthropic\Http\RetryPolicy;

$client = Client::builder()
    ->withApiKey(getenv('ANTHROPIC_API_KEY'))
    ->withAnthropicVersion('2023-06-01')
    ->withAnthropicBeta('prompt-caching-2024-07-31')
    ->withRetryPolicy(new RetryPolicy(maxAttempts: 5, baseDelay: 1.0))
    ->withTimeout(120.0)
    ->build();
```

## Streaming

```php
use AlleAI\Anthropic\Streaming\Events\ContentBlockDeltaEvent;
use AlleAI\Anthropic\Streaming\Events\Deltas\TextDelta;

$stream = $client->messages()->stream(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    messages: [['role' => 'user', 'content' => 'Tell me a story.']],
);

foreach ($stream as $event) {
    if ($event instanceof ContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
        echo $event->delta->text;
    }
}

$final = $stream->toMessage();   // aggregated MessageResponse
echo "\nDone in {$final->usage->outputTokens} tokens.\n";
```

## Tool use

```php
use AlleAI\Anthropic\Tools\ClassTool;
use AlleAI\Anthropic\Tools\Schema\Attributes\Enum;
use AlleAI\Anthropic\Tools\Schema\Attributes\Param;
use AlleAI\Anthropic\Tools\ToolSet;

final class GetWeather extends ClassTool
{
    public function name(): string        { return 'get_weather'; }
    public function description(): string { return 'Get current weather'; }

    /** @return array<string, mixed> */
    protected function runTool(
        #[Param('City name')] string $city,
        #[Param('Units')] #[Enum('c', 'f')] string $units = 'c',
    ): array {
        return ['city' => $city, 'temp' => 24, 'units' => $units];
    }
}

$loop = $client->messages()->toolLoop(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 4096,
    messages: [['role' => 'user', 'content' => 'Weather in Accra and Tokyo?']],
    tools: new ToolSet(new GetWeather()),
);

$final = $loop->run();   // automatic tool-call round-trips until end_turn
echo $final->text();
```

## Vision

```php
use AlleAI\Anthropic\Messages\Content\ImageBlock;
use AlleAI\Anthropic\Messages\Content\TextBlock;

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    messages: [[
        'role' => 'user',
        'content' => [
            ImageBlock::fromFile(__DIR__ . '/diagram.png'),
            TextBlock::of('Describe this diagram.'),
        ],
    ]],
);
```

## Prompt caching

```php
use AlleAI\Anthropic\Messages\Content\CacheControl;
use AlleAI\Anthropic\Messages\Content\TextBlock;

$response = $client->messages()->create(
    model: Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    system: [
        TextBlock::of('You are a helpful assistant.'),
        TextBlock::of($longCorpus)->withCacheControl(CacheControl::ephemeral('1h')),
    ],
    messages: [['role' => 'user', 'content' => 'Summarize.']],
);

echo "Cache read: {$response->usage->cacheReadInputTokens}\n";
```

## Extended thinking

```php
use AlleAI\Anthropic\Messages\ThinkingConfig;
use AlleAI\Anthropic\Messages\Content\ThinkingBlock;
use AlleAI\Anthropic\Messages\Content\TextBlock;

$response = $client->messages()->create(
    model: Model::CLAUDE_OPUS_4_7,
    maxTokens: 16_000,
    thinking: ThinkingConfig::enabled(budgetTokens: 10_000),
    messages: [['role' => 'user', 'content' => 'Prove there are infinitely many primes.']],
);

foreach ($response->content as $block) {
    if ($block instanceof ThinkingBlock) {
        // internal reasoning — typically logged or hidden from end users
    }
    if ($block instanceof TextBlock) {
        echo $block->text;
    }
}
```

## Error handling

```php
use AlleAI\Anthropic\Exceptions\AnthropicException;
use AlleAI\Anthropic\Exceptions\AuthenticationException;
use AlleAI\Anthropic\Exceptions\RateLimitException;
use AlleAI\Anthropic\Exceptions\OverloadedException;

try {
    $response = $client->messages()->create(/* ... */);
} catch (AuthenticationException $e) {
    // 401 — bad API key
} catch (RateLimitException $e) {
    // 429 — back off
    sleep($e->retryAfter() ?? 30);
} catch (OverloadedException $e) {
    // 529 — Anthropic capacity issue
} catch (AnthropicException $e) {
    // anything else from this SDK
    error_log('Anthropic API call failed: ' . $e->getMessage());
}
```

## Alternative deployments

Same client, different backend.

### AWS Bedrock

```bash
composer require aws/aws-sdk-php
```

```php
use AlleAI\Anthropic\Auth\BedrockAuth;

$client = Client::builder()
    ->withAuth(BedrockAuth::fromEnvironment(region: 'us-east-1'))
    ->build();

// Use Bedrock model id format:
$response = $client->messages()->create(
    model: 'anthropic.claude-sonnet-4-7-v1:0',
    maxTokens: 1024,
    messages: [['role' => 'user', 'content' => 'Hello']],
);
```

`BedrockAuth::fromEnvironment()` uses the AWS default credentials chain (env vars, `~/.aws/credentials`, IAM roles, etc.). The auth provider rewrites the URL to `bedrock-runtime.{region}.amazonaws.com`, transforms the body to Bedrock's expected shape, and signs with SigV4.

### Google Vertex AI

```bash
composer require google/auth
```

```php
use AlleAI\Anthropic\Auth\VertexAuth;

$client = Client::builder()
    ->withAuth(VertexAuth::fromEnvironment(
        projectId: 'my-gcp-project',
        region: 'us-east5',
    ))
    ->build();

$response = $client->messages()->create(
    model: 'claude-sonnet-4-7@20260101',  // Vertex publisher format
    maxTokens: 1024,
    messages: [['role' => 'user', 'content' => 'Hello']],
);
```

`VertexAuth::fromEnvironment()` uses Google ADC for tokens. Pass `projectId` explicitly or set `GOOGLE_CLOUD_PROJECT`; same for region with `GOOGLE_CLOUD_REGION`.

## Custom HTTP client

The SDK auto-discovers a PSR-18 client via `php-http/discovery`. Inject your own to gain full control:

```php
use GuzzleHttp\Client as GuzzleClient;

$guzzle = new GuzzleClient([
    'timeout' => 60,
    'proxy' => 'http://corporate-proxy:3128',
]);

$client = Client::builder()
    ->withApiKey(getenv('ANTHROPIC_API_KEY'))
    ->withHttpClient($guzzle)
    ->build();
```

## Roadmap

| Tag | Status | Adds |
|---|---|---|
| `v2.0.0-beta.1` | shipped | Messages create + stream, tool use, vision, prompt caching, extended thinking, citations, Files, Batches, MCP connector, Models listing, PSR-3 logging, Bedrock + Vertex auth, 12 examples, full test suite, PHPStan level 9, deprecation shim |
| `v2.0.0` | GA | Beta-feedback bug fixes, mutation testing, docs site |
| `v2.1.0` | | Async / concurrent helpers; observability extras |
| `v2.2.0` | | Alle-AI sister client (`AlleAI\AlleAI\Client`) for multi-model fan-out |
| `v3.0.0` | | Remove `Alle_AI\Anthropic\AnthropicAPI` deprecation shim |

## Migration from v1.x

The single-class v1 surface (`Alle_AI\Anthropic\AnthropicAPI::generateText()`) is preserved as a deprecation shim. Existing code keeps working — every call emits an `E_USER_DEPRECATED` notice. Set `ALLE_AI_ANTHROPIC_FAIL_ON_DEPRECATED=1` to convert notices into exceptions during migration.

See [UPGRADING.md](UPGRADING.md) for the full v1 → v2 guide.

## About Alle-AI

This library is built and maintained by [Alle-AI](https://alle-ai.com) — *Your All-In-One AI Platform*. Alle-AI gives you a single interface to compare and combine outputs from frontier models (Claude, GPT, Gemini, Llama, and more). If you build on Anthropic and want to evaluate alternatives in the same workflow, check out the platform.

## Support

- **Bugs / feature requests:** [GitHub Issues](https://github.com/Alle-AI/anthropic-api-php/issues)
- **Discussion:** [GitHub Discussions](https://github.com/Alle-AI/anthropic-api-php/discussions)
- **Email:** [dickson@alle-ai.com](mailto:dickson@alle-ai.com)
- **Security disclosures:** see [SECURITY.md](SECURITY.md)

## License

MIT © 2023–present [Alle-AI](https://alle-ai.com). See [LICENSE](LICENSE).
