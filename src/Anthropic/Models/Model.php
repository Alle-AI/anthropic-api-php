<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Models;

/**
 * Anthropic model identifier.
 *
 * Constants cover the well-known stable aliases. Use {@see Model::of()} to
 * pass any model id Anthropic ships — the SDK never blocks you on an
 * unknown id.
 *
 * ```php
 * $client->messages()->create(model: Model::CLAUDE_SONNET_4_5, ...);
 * $client->messages()->create(model: Model::of('claude-sonnet-5-0'), ...);
 * $client->messages()->create(model: 'claude-haiku-4-5', ...); // raw string also accepted
 * ```
 */
final readonly class Model implements \Stringable
{
    /**
     * Stable aliases. Anthropic also accepts dated snapshots like
     * "claude-sonnet-4-5-20250929" — pass those via {@see Model::of()}.
     */
    public const CLAUDE_OPUS_4_7 = 'claude-opus-4-7';
    public const CLAUDE_SONNET_4_7 = 'claude-sonnet-4-7';
    public const CLAUDE_HAIKU_4_5 = 'claude-haiku-4-5';
    public const CLAUDE_SONNET_4_6 = 'claude-sonnet-4-6';
    public const CLAUDE_SONNET_4_5 = 'claude-sonnet-4-5';
    public const CLAUDE_OPUS_4_1 = 'claude-opus-4-1';
    public const CLAUDE_OPUS_4 = 'claude-opus-4';
    public const CLAUDE_SONNET_4 = 'claude-sonnet-4';
    public const CLAUDE_3_7_SONNET = 'claude-3-7-sonnet-latest';
    public const CLAUDE_3_5_HAIKU = 'claude-3-5-haiku-latest';

    public ModelFamily $family;

    private function __construct(public string $id)
    {
        if (trim($id) === '') {
            throw new \InvalidArgumentException('Model id cannot be empty.');
        }

        $this->family = ModelFamily::guess($id);
    }

    /**
     * Forward-compatible factory — accepts any model id, present or future.
     */
    public static function of(string $id): self
    {
        return new self($id);
    }

    public function __toString(): string
    {
        return $this->id;
    }
}
