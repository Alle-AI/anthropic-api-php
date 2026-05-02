<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools\Server;

/**
 * Bash tool — paired with computer use. The model emits `tool_use` blocks
 * with shell commands; the host app runs them in a sandbox and returns
 * stdout/stderr in a `tool_result`.
 */
final readonly class BashToolDefinition implements ServerToolDefinition
{
    public const BETA_HEADER = 'computer-use-2025-01-24';
    public const TYPE = 'bash_20250124';
    public const NAME = 'bash';

    public static function create(): self
    {
        return new self();
    }

    public function toArray(): array
    {
        return ['type' => self::TYPE, 'name' => self::NAME];
    }

    public function betaHeader(): ?string
    {
        return self::BETA_HEADER;
    }
}
