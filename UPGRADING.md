# Upgrading from v1.x to v2.x

v2 is a complete rewrite. The v1 single-class surface (`Alle_AI\Anthropic\AnthropicAPI`) keeps working as a deprecation shim through v2.x and is removed in v3.0.

This guide walks you through migrating to the new API.

## TL;DR

```php
// v1
$api = new \Alle_AI\Anthropic\AnthropicAPI($apiKey, '2023-06-01');
$response = $api->generateText([
    'prompt' => "\n\nHuman: Hello\n\nAssistant:",
    'model' => 'claude-2.1',
    'max_tokens_to_sample' => 300,
], 'complete');
echo $response['completion'];

// v2
$client = \AlleAI\Anthropic\Client::fromApiKey($apiKey);
$response = $client->messages()->create(
    model: \AlleAI\Anthropic\Models\Model::CLAUDE_SONNET_4_7,
    maxTokens: 300,
    messages: [['role' => 'user', 'content' => 'Hello']],
);
echo $response->text();
```

## What changed

| v1 | v2 |
|---|---|
| `Alle_AI\Anthropic\AnthropicAPI` | `AlleAI\Anthropic\Client` |
| `generateText($data, 'complete')` (legacy completions endpoint) | `Client->messages()->create(...)` (Messages API) |
| Raw cURL, no error handling | PSR-18 HTTP client, structured exception hierarchy |
| `claude-2.1` (deprecated by Anthropic) | `Model::CLAUDE_SONNET_4_7`, `Model::CLAUDE_OPUS_4_7`, etc. |
| Manual `\n\nHuman:` / `\n\nAssistant:` prompt format | Structured `messages` array with `role` and `content` |
| `$response['completion']` | `$response->text()` (typed `MessageResponse`) |
| No streaming | Generator-based SSE *(coming in v2.0.0-beta.2)* |
| No tool use, vision, caching | First-class support |

## Step-by-step

### 1. Bump PHP

v2 requires **PHP 8.2 or newer**. Verify with `php -v` before upgrading.

### 2. Update composer

```bash
composer require alle-ai/anthropic-api-php:^2.0
composer require guzzlehttp/guzzle nyholm/psr7   # if you don't already have a PSR-18 client
```

### 3. Run your existing code

It still works. You'll see deprecation notices for every call:

```
Deprecated: Alle_AI\Anthropic\AnthropicAPI is deprecated since 2.0.0 ...
```

To make these fail loudly so you can find every call site:

```bash
ALLE_AI_ANTHROPIC_FAIL_ON_DEPRECATED=1 php your-script.php
```

### 4. Migrate to the new client

Replace each call site:

#### Simple text generation

```php
// before
$api = new \Alle_AI\Anthropic\AnthropicAPI($apiKey, '2023-06-01');
$resp = $api->generateText([
    'prompt' => "\n\nHuman: How many toes do dogs have?\n\nAssistant:",
    'model' => 'claude-2.1',
    'max_tokens_to_sample' => 300,
]);
echo $resp['completion'];

// after
$client = \AlleAI\Anthropic\Client::fromApiKey($apiKey);
$resp = $client->messages()->create(
    model: \AlleAI\Anthropic\Models\Model::CLAUDE_SONNET_4_7,
    maxTokens: 300,
    messages: [['role' => 'user', 'content' => 'How many toes do dogs have?']],
);
echo $resp->text();
```

#### Multi-turn conversation

```php
// after
$messages = [
    ['role' => 'user',      'content' => 'Hello!'],
    ['role' => 'assistant', 'content' => 'Hi there. How can I help?'],
    ['role' => 'user',      'content' => 'Tell me a joke.'],
];

$resp = $client->messages()->create(
    model: \AlleAI\Anthropic\Models\Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    messages: $messages,
);
```

#### System prompt

```php
$resp = $client->messages()->create(
    model: \AlleAI\Anthropic\Models\Model::CLAUDE_SONNET_4_7,
    maxTokens: 1024,
    system: 'You are a helpful assistant who answers in haiku.',
    messages: [['role' => 'user', 'content' => 'Why is the sky blue?']],
);
```

### 5. Replace deprecated models

Anthropic deprecated the `claude-1.x` and `claude-2.x` lines. Pick a current model:

| Old | New |
|---|---|
| `claude-1`, `claude-1.3`, `claude-instant-1` | `Model::CLAUDE_HAIKU_4_5` (cheap/fast) |
| `claude-2`, `claude-2.1` | `Model::CLAUDE_SONNET_4_7` (balanced) |
| any heavy reasoning workload | `Model::CLAUDE_OPUS_4_7` |

For an unreleased model id you want to try before a SDK release ships, use `Model::of('claude-sonnet-5-0')`.

### 6. Adopt the typed exception hierarchy

```php
use AlleAI\Anthropic\Exceptions\AnthropicException;
use AlleAI\Anthropic\Exceptions\RateLimitException;

try {
    $resp = $client->messages()->create(/* ... */);
} catch (RateLimitException $e) {
    sleep($e->retryAfter() ?? 30);
} catch (AnthropicException $e) {
    // any SDK error — has $e->status, $e->errorType, $e->requestId
}
```

## Removal timeline

- **v2.x** — `Alle_AI\Anthropic\AnthropicAPI` works with deprecation notice.
- **v3.0** — shim removed. v1 code stops working.

We watch Packagist install statistics for v1 usage and will not cut v3.0 while non-trivial v1 traffic remains.

## Need help?

Open an issue or email [dickson@alle-ai.com](mailto:dickson@alle-ai.com).
