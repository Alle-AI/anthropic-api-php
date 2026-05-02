<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools\Server;

/**
 * Text editor tool — paired with computer use. The model emits
 * `tool_use` blocks with editor commands (view / create / str_replace /
 * insert / undo_edit); the host app applies them.
 */
final readonly class TextEditorToolDefinition implements ServerToolDefinition
{
    public const BETA_HEADER = 'computer-use-2025-01-24';
    public const TYPE = 'text_editor_20250124';
    public const NAME = 'str_replace_editor';

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
