<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Assistant-emitted block requesting that a tool be executed.
 */
final readonly class ToolUseBlock implements ContentBlock
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $input,
    ) {}

    public function type(): string
    {
        return 'tool_use';
    }

    public function toArray(): array
    {
        return [
            'type' => 'tool_use',
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->input,
        ];
    }
}
