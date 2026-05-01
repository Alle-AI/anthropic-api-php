<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Models;

enum ModelFamily: string
{
    case OPUS = 'opus';
    case SONNET = 'sonnet';
    case HAIKU = 'haiku';
    case UNKNOWN = 'unknown';

    /**
     * Best-effort detection from a model id like "claude-opus-4-7" or
     * "claude-sonnet-4-5-20250929".
     */
    public static function guess(string $modelId): self
    {
        $lower = strtolower($modelId);

        if (str_contains($lower, 'opus')) {
            return self::OPUS;
        }
        if (str_contains($lower, 'sonnet')) {
            return self::SONNET;
        }
        if (str_contains($lower, 'haiku')) {
            return self::HAIKU;
        }

        return self::UNKNOWN;
    }
}
