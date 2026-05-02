# anthropic-api-php

> The go-to PHP library for the Anthropic API. Maintained by [Alle-AI](https://alle-ai.com).

[![Latest Version](https://img.shields.io/packagist/v/alle-ai/anthropic-api-php.svg?style=flat-square)](https://packagist.org/packages/alle-ai/anthropic-api-php)
[![Total Downloads](https://img.shields.io/packagist/dt/alle-ai/anthropic-api-php.svg?style=flat-square)](https://packagist.org/packages/alle-ai/anthropic-api-php)
[![PHP Version](https://img.shields.io/packagist/php-v/alle-ai/anthropic-api-php.svg?style=flat-square)](https://packagist.org/packages/alle-ai/anthropic-api-php)
[![License](https://img.shields.io/packagist/l/alle-ai/anthropic-api-php.svg?style=flat-square)](LICENSE)

A first-class PHP client for the Anthropic Messages API. Built for Claude 4 and beyond — Messages, streaming, tool use, vision, prompt caching, extended thinking, MCP connector, Files, Batches.

> **Looking for the v1.x docs?** See the [`1.x`](https://github.com/Alle-AI/anthropic-api-php/tree/1.x) branch. The v1 surface (`Alle_AI\Anthropic\AnthropicAPI`) is preserved as a deprecation shim through the v2.x line and removed in v3.0. See [UPGRADING.md](UPGRADING.md).

## Features

- **Messages API** — typed requests and responses for `POST /v1/messages`
- **Streaming** — Generator-based SSE iterator with `->toMessage()` aggregator *(coming in 2.0.0-beta.2)*
- **Tool use** — closure tools, class-based tools with reflection-driven JSON Schema, automatic tool-loop helper *(coming in 2.0.0-beta.2)*
- **Vision** — `ImageBlock::fromFile()` / `fromUrl()` / `fromBase64()`
- **Prompt caching** — `cache_control` on any block; `Usage::$cacheReadInputTokens` in the response
- **Extended thinking** — `ThinkingConfig::enabled(budgetTokens: 10_000)` for reasoning models
- **MCP connector** — call any remote MCP server via Anthropic's hosted connector *(coming in 2.1.0)*
- **PSR-18 / PSR-17** — bring any HTTP client (Guzzle, Symfony HttpClient, Buzz, …)
- **Retries** — exponential backoff with jitter, honors `Retry-After`, idempotency keys auto-attached
- **Pluggable auth** — API key, Bearer token. Bedrock and Vertex AI in optional sibling packages

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
| `v2.0.0-beta.1` | shipping now | Messages create, vision, caching, extended thinking, retries, error hierarchy, PSR-18, deprecation shim |
| `v2.0.0-beta.2` | next | Streaming (SSE), tool use, automatic tool-loop, full PHPUnit suite, GitHub Actions CI |
| `v2.0.0` | GA | Citations, docs site, mutation testing |
| `v2.1.0` | | Files API, Batches, MCP connector under `Beta\` |
| `v2.2.0` | | Sibling packages: `alle-ai/anthropic-bedrock`, `alle-ai/anthropic-vertex`. PSR-3 logging middleware |
| `v2.3.0` | | Alle-AI sister client (`AlleAI\AlleAI\Client`) for multi-model fan-out |
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
