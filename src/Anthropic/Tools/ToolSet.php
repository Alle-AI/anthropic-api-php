<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools;

/**
 * A collection of tools indexed by name.
 */
final class ToolSet
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct(Tool ...$tools)
    {
        foreach ($tools as $tool) {
            $this->add($tool);
        }
    }

    public function add(Tool $tool): self
    {
        $name = $tool->name();
        if (isset($this->tools[$name])) {
            throw new \InvalidArgumentException(sprintf('Duplicate tool name: %s', $name));
        }
        $this->tools[$name] = $tool;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): Tool
    {
        if (!isset($this->tools[$name])) {
            throw new \OutOfBoundsException(sprintf('No tool registered with name "%s".', $name));
        }

        return $this->tools[$name];
    }

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function isEmpty(): bool
    {
        return $this->tools === [];
    }

    /**
     * Wire-format definitions ready for the `tools` field.
     *
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(static fn (Tool $t): array => $t->toArray(), array_values($this->tools));
    }
}
