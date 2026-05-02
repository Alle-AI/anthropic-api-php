<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools\Server;

/**
 * Computer-use tool definition. The model emits `tool_use` blocks with
 * computer-specific actions (mouse / keyboard / screenshot); the host
 * app is responsible for executing them and returning a `tool_result`
 * with the new screenshot.
 *
 * ```php
 * $tool = ComputerToolDefinition::create(
 *     displayWidthPx: 1024,
 *     displayHeightPx: 768,
 * );
 * ```
 */
final readonly class ComputerToolDefinition implements ServerToolDefinition
{
    public const BETA_HEADER = 'computer-use-2025-01-24';
    public const TYPE = 'computer_20250124';
    public const NAME = 'computer';

    private function __construct(
        public int $displayWidthPx,
        public int $displayHeightPx,
        public ?int $displayNumber = null,
    ) {
        if ($displayWidthPx <= 0 || $displayHeightPx <= 0) {
            throw new \InvalidArgumentException('ComputerToolDefinition: display dimensions must be positive.');
        }
    }

    public static function create(
        int $displayWidthPx,
        int $displayHeightPx,
        ?int $displayNumber = null,
    ): self {
        return new self($displayWidthPx, $displayHeightPx, $displayNumber);
    }

    public function toArray(): array
    {
        $out = [
            'type' => self::TYPE,
            'name' => self::NAME,
            'display_width_px' => $this->displayWidthPx,
            'display_height_px' => $this->displayHeightPx,
        ];

        if ($this->displayNumber !== null) {
            $out['display_number'] = $this->displayNumber;
        }

        return $out;
    }

    public function betaHeader(): ?string
    {
        return self::BETA_HEADER;
    }
}
