<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools\Server;

/**
 * A "server-side" tool definition Anthropic exposes natively (web search,
 * computer use, bash, text editor, code execution, etc.). The SDK doesn't
 * execute these — Anthropic does — so the value object only carries the
 * shape of the entry that goes into the request's `tools` array.
 *
 * Pass instances straight into {@see \AlleAI\Anthropic\Resources\Messages::create()}'s
 * `$tools` parameter via `->toArray()`:
 *
 * ```php
 * $client->messages()->create(
 *     model: Model::CLAUDE_SONNET_4_7,
 *     maxTokens: 1024,
 *     messages: [['role' => 'user', 'content' => 'Search the web for AI news.']],
 *     tools: [WebSearchToolDefinition::create(maxUses: 3)->toArray()],
 *     extraHeaders: ['anthropic-beta' => WebSearchToolDefinition::BETA_HEADER],
 * );
 * ```
 */
interface ServerToolDefinition
{
    /**
     * @return array<string, mixed>  wire-format definition
     */
    public function toArray(): array;

    /**
     * Beta header value Anthropic requires for this tool, or null if it
     * has graduated.
     */
    public function betaHeader(): ?string;
}
