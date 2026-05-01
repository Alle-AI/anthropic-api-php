<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages;

enum StopReason: string
{
    case END_TURN = 'end_turn';
    case MAX_TOKENS = 'max_tokens';
    case STOP_SEQUENCE = 'stop_sequence';
    case TOOL_USE = 'tool_use';
    case PAUSE_TURN = 'pause_turn';
    case REFUSAL = 'refusal';
    case MODEL_CONTEXT_WINDOW_EXCEEDED = 'model_context_window_exceeded';

    /**
     * Tolerant constructor — Anthropic may add new stop reasons. Returns
     * null for unknown values rather than throwing, so callers can fall
     * back to inspecting the raw response.
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }
}
