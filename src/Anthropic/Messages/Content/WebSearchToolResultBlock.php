<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Result block emitted by Anthropic after running a `web_search`
 * server-side tool call. `$content` is either:
 *   - a list of `web_search_result` entries (each a `{type, url, title,
 *     encrypted_content, page_age?}` shape), or
 *   - an error envelope `{type: 'web_search_tool_result_error',
 *     error_code: '...'}` when the search failed.
 *
 * The DTO keeps both shapes accessible — typed helpers expose the common
 * fields and `$raw` holds the original payload.
 */
final readonly class WebSearchToolResultBlock implements ContentBlock
{
    /**
     * @param  array<array-key, mixed>|string  $content  list of results, or error envelope, or string
     */
    public function __construct(
        public string $toolUseId,
        public array|string $content,
    ) {
    }

    public function type(): string
    {
        return 'web_search_tool_result';
    }

    public function toArray(): array
    {
        return [
            'type' => 'web_search_tool_result',
            'tool_use_id' => $this->toolUseId,
            'content' => $this->content,
        ];
    }

    public function isError(): bool
    {
        return is_array($this->content)
            && isset($this->content['type'])
            && $this->content['type'] === 'web_search_tool_result_error';
    }

    public function errorCode(): ?string
    {
        if (!$this->isError() || !is_array($this->content)) {
            return null;
        }

        return isset($this->content['error_code']) && is_string($this->content['error_code'])
            ? $this->content['error_code']
            : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function results(): array
    {
        if ($this->isError() || !is_array($this->content)) {
            return [];
        }

        $out = [];
        foreach ($this->content as $entry) {
            if (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $out[] = $entry;
            }
        }

        return $out;
    }
}
