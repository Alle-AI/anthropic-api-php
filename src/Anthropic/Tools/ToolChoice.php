<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools;

/**
 * How Claude should pick a tool: auto (the default), any tool must be used,
 * a specific named tool, or no tool at all.
 */
final readonly class ToolChoice
{
    /**
     * @param  'auto'|'any'|'tool'|'none'  $type
     */
    private function __construct(
        public string $type,
        public ?string $name = null,
        public ?bool $disableParallelToolUse = null,
    ) {
    }

    public static function auto(?bool $disableParallel = null): self
    {
        return new self('auto', null, $disableParallel);
    }

    public static function any(?bool $disableParallel = null): self
    {
        return new self('any', null, $disableParallel);
    }

    public static function tool(string $name, ?bool $disableParallel = null): self
    {
        return new self('tool', $name, $disableParallel);
    }

    public static function none(): self
    {
        return new self('none');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['type' => $this->type];
        if ($this->name !== null) {
            $out['name'] = $this->name;
        }
        if ($this->disableParallelToolUse !== null) {
            $out['disable_parallel_tool_use'] = $this->disableParallelToolUse;
        }

        return $out;
    }
}
