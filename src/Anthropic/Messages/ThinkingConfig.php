<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages;

/**
 * Configuration for extended thinking on Claude reasoning models.
 *
 * ```php
 * $client->messages()->create(
 *     model: Model::CLAUDE_OPUS_4_7,
 *     thinking: ThinkingConfig::enabled(budgetTokens: 10_000),
 *     // ...
 * );
 * ```
 */
final readonly class ThinkingConfig
{
    /**
     * @param  'enabled'|'disabled'  $type
     */
    private function __construct(
        public string $type,
        public ?int $budgetTokens,
    ) {
    }

    public static function enabled(int $budgetTokens): self
    {
        if ($budgetTokens < 1024) {
            throw new \InvalidArgumentException('budgetTokens must be at least 1024.');
        }

        return new self('enabled', $budgetTokens);
    }

    public static function disabled(): self
    {
        return new self('disabled', null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = ['type' => $this->type];
        if ($this->budgetTokens !== null) {
            $out['budget_tokens'] = $this->budgetTokens;
        }

        return $out;
    }
}
