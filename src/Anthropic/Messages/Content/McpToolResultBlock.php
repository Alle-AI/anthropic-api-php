<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Result block paired with a preceding {@see McpToolUseBlock}. Anthropic's
 * hosted MCP connector executes the tool against the remote server and
 * surfaces the result here without a round-trip back to your code.
 */
final readonly class McpToolResultBlock implements ContentBlock
{
    public function __construct(
        public string $toolUseId,
        public mixed $content,
        public bool $isError = false,
    ) {
    }

    public function type(): string
    {
        return 'mcp_tool_result';
    }

    public function toArray(): array
    {
        $out = [
            'type' => 'mcp_tool_result',
            'tool_use_id' => $this->toolUseId,
            'content' => $this->content,
        ];
        if ($this->isError) {
            $out['is_error'] = true;
        }

        return $out;
    }
}
