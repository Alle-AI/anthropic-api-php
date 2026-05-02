<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Beta\Mcp;

/**
 * Tool-approval policy for an {@see McpServer}. Anthropic supports two
 * shapes:
 *   - 'always' / 'never' / 'unless_disallowed' (a string mode)
 *   - { mode: 'always'|..., always_allowed: [...], never_allowed: [...] }
 *
 * Use the factories to build the right shape; both serialise via toArray().
 */
final readonly class McpToolApproval
{
    /**
     * @param  list<string>|null  $alwaysAllowed
     * @param  list<string>|null  $neverAllowed
     */
    private function __construct(
        public string $mode,
        public ?array $alwaysAllowed = null,
        public ?array $neverAllowed = null,
    ) {
    }

    public static function always(): self
    {
        return new self('always');
    }

    public static function never(): self
    {
        return new self('never');
    }

    public static function unlessDisallowed(): self
    {
        return new self('unless_disallowed');
    }

    /**
     * @param  list<string>|null  $alwaysAllowed
     * @param  list<string>|null  $neverAllowed
     */
    public static function custom(
        string $mode,
        ?array $alwaysAllowed = null,
        ?array $neverAllowed = null,
    ): self {
        return new self($mode, $alwaysAllowed, $neverAllowed);
    }

    /**
     * @return string|array<string, mixed>
     */
    public function toWire(): string|array
    {
        if ($this->alwaysAllowed === null && $this->neverAllowed === null) {
            return $this->mode;
        }

        $out = ['mode' => $this->mode];
        if ($this->alwaysAllowed !== null) {
            $out['always_allowed'] = $this->alwaysAllowed;
        }
        if ($this->neverAllowed !== null) {
            $out['never_allowed'] = $this->neverAllowed;
        }

        return $out;
    }
}
