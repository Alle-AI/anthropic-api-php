<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Decodes wire-format content block arrays into typed ContentBlock objects.
 *
 * Unknown block types fall back to {@see UnknownBlock} so users can still
 * inspect them via the raw payload.
 */
final class ContentBlockFactory
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): ContentBlock
    {
        $type = isset($raw['type']) && is_string($raw['type']) ? $raw['type'] : '';

        return match ($type) {
            'text' => new TextBlock(
                text: isset($raw['text']) && is_string($raw['text']) ? $raw['text'] : '',
                citations: self::parseCitations($raw),
            ),
            'image' => self::imageFromArray($raw),
            'tool_use' => new ToolUseBlock(
                id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : '',
                name: isset($raw['name']) && is_string($raw['name']) ? $raw['name'] : '',
                /** @phpstan-ignore-next-line — defensive cast */
                input: isset($raw['input']) && is_array($raw['input']) ? $raw['input'] : [],
            ),
            'tool_result' => new ToolResultBlock(
                toolUseId: isset($raw['tool_use_id']) && is_string($raw['tool_use_id']) ? $raw['tool_use_id'] : '',
                content: $raw['content'] ?? null,
                isError: (bool) ($raw['is_error'] ?? false),
            ),
            'thinking' => new ThinkingBlock(
                thinking: isset($raw['thinking']) && is_string($raw['thinking']) ? $raw['thinking'] : '',
                signature: isset($raw['signature']) && is_string($raw['signature']) ? $raw['signature'] : null,
            ),
            'redacted_thinking' => new RedactedThinkingBlock(
                data: isset($raw['data']) && is_string($raw['data']) ? $raw['data'] : '',
            ),
            'server_tool_use' => new ServerToolUseBlock(
                id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : '',
                name: isset($raw['name']) && is_string($raw['name']) ? $raw['name'] : '',
                input: isset($raw['input']) && is_array($raw['input']) ? self::asStringMap($raw['input']) : [],
            ),
            'web_search_tool_result' => new WebSearchToolResultBlock(
                toolUseId: isset($raw['tool_use_id']) && is_string($raw['tool_use_id']) ? $raw['tool_use_id'] : '',
                content: self::normalizeWebSearchContent($raw['content'] ?? null),
            ),
            'mcp_tool_use' => new McpToolUseBlock(
                id: isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : '',
                name: isset($raw['name']) && is_string($raw['name']) ? $raw['name'] : '',
                serverName: isset($raw['server_name']) && is_string($raw['server_name']) ? $raw['server_name'] : '',
                input: isset($raw['input']) && is_array($raw['input']) ? self::asStringMap($raw['input']) : [],
            ),
            'mcp_tool_result' => new McpToolResultBlock(
                toolUseId: isset($raw['tool_use_id']) && is_string($raw['tool_use_id']) ? $raw['tool_use_id'] : '',
                content: $raw['content'] ?? null,
                isError: (bool) ($raw['is_error'] ?? false),
            ),
            default => new UnknownBlock($type, $raw),
        };
    }

    /**
     * Normalize the `content` field on a `web_search_tool_result` block.
     * Anthropic sends a list of result entries on success, or an error
     * envelope `{type:'web_search_tool_result_error', error_code:'...'}`
     * on failure; pass through strings unchanged.
     *
     * @return array<array-key, mixed>|string
     */
    private static function normalizeWebSearchContent(mixed $raw): array|string
    {
        if (is_string($raw)) {
            return $raw;
        }
        if (is_array($raw)) {
            return $raw;
        }

        return [];
    }

    /**
     * @param  array<array-key, mixed>  $raw
     *
     * @return array<string, mixed>
     */
    private static function asStringMap(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     *
     * @return list<Citation>
     */
    private static function parseCitations(array $raw): array
    {
        if (!isset($raw['citations']) || !is_array($raw['citations'])) {
            return [];
        }

        $out = [];
        foreach ($raw['citations'] as $entry) {
            if (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $out[] = Citation::fromArray($entry);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function imageFromArray(array $raw): ImageBlock
    {
        $source = isset($raw['source']) && is_array($raw['source']) ? $raw['source'] : [];
        $sourceType = isset($source['type']) && is_string($source['type']) ? $source['type'] : 'base64';

        if ($sourceType === 'url') {
            return new ImageBlock(
                sourceType: 'url',
                data: isset($source['url']) && is_string($source['url']) ? $source['url'] : '',
            );
        }

        return new ImageBlock(
            sourceType: 'base64',
            data: isset($source['data']) && is_string($source['data']) ? $source['data'] : '',
            mediaType: isset($source['media_type']) && is_string($source['media_type']) ? $source['media_type'] : null,
        );
    }
}
