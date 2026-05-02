<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Beta;

/**
 * Registry of well-known `anthropic-beta` header values.
 *
 * Anthropic gates new API capabilities behind this header. The values
 * rotate as features graduate; constants here track the current shapes
 * and are updated alongside the SDK. Pass them to
 * {@see \AlleAI\Anthropic\ClientBuilder::withAnthropicBeta()}:
 *
 * ```php
 * $client = Client::builder()
 *     ->withApiKey($key)
 *     ->withAnthropicBeta(BetaHeaders::PROMPT_CACHING, BetaHeaders::FILES_API)
 *     ->build();
 * ```
 *
 * Anthropic may ship new beta values between SDK releases. Pass any
 * string directly to `withAnthropicBeta()` — these constants are
 * conveniences, not the only allowed values.
 */
final class BetaHeaders
{
    public const PROMPT_CACHING = 'prompt-caching-2024-07-31';
    public const PROMPT_CACHING_EXTENDED = 'extended-cache-ttl-2025-04-11';
    public const MESSAGE_BATCHES = 'message-batches-2024-09-24';
    public const FILES_API = 'files-api-2025-04-14';
    public const COMPUTER_USE = 'computer-use-2025-01-24';
    public const MCP_CLIENT = 'mcp-client-2025-04-04';
    public const TOKEN_EFFICIENT_TOOLS = 'token-efficient-tools-2025-02-19';
    public const EXTENDED_THINKING = 'interleaved-thinking-2025-05-14';
    public const CONTEXT_1M = 'context-1m-2025-08-07';

    private function __construct()
    {
    }
}
