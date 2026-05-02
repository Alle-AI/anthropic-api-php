<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Assistant block emitted when Claude invokes a server-side tool
 * (e.g. `web_search`). Anthropic runs the tool itself; the corresponding
 * result arrives in a {@see WebSearchToolResultBlock} (or similar) in
 * the same response.
 */
final readonly class ServerToolUseBlock implements ContentBlock
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $input,
    ) {
    }

    public function type(): string
    {
        return 'server_tool_use';
    }

    public function toArray(): array
    {
        return [
            'type' => 'server_tool_use',
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->input,
        ];
    }
}
