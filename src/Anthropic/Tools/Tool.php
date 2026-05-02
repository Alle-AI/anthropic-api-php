<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools;

use AlleAI\Anthropic\Messages\Content\CacheControl;

/**
 * A tool that Claude can call. Implementations must produce a JSON Schema
 * for their input and execute calls with that input.
 *
 * Use {@see ClosureTool::create()} for inline closures, or
 * {@see ClassTool} as a base class for richer behavior with reflection-driven
 * schema generation.
 */
interface Tool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema for the tool's input. Anthropic expects an `object` schema
     * with a `properties` map.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Execute the tool with the input emitted by Claude. Return any
     * JSON-serializable value — it will be wrapped into a `tool_result`
     * block by the {@see ToolLoop}.
     *
     * @param  array<string, mixed>  $input
     */
    public function run(array $input): mixed;

    /**
     * Optional cache_control for the tool definition itself.
     */
    public function cacheControl(): ?CacheControl;

    /**
     * Wire-format definition suitable for the `tools` field in a Messages
     * request.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
