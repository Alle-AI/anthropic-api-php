<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Server-side tool-use block emitted when the model invokes a tool on a
 * remote MCP server (via Anthropic's hosted connector). The corresponding
 * {@see McpToolResultBlock} arrives in the same response — Anthropic
 * executes the tool for you, no client round-trip required.
 */
final readonly class McpToolUseBlock implements ContentBlock
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $serverName,
        public array $input,
    ) {
    }

    public function type(): string
    {
        return 'mcp_tool_use';
    }

    public function toArray(): array
    {
        return [
            'type' => 'mcp_tool_use',
            'id' => $this->id,
            'name' => $this->name,
            'server_name' => $this->serverName,
            'input' => $this->input,
        ];
    }
}
