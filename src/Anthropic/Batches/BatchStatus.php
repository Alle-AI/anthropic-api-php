<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Batches;

enum BatchStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case CANCELING = 'canceling';
    case ENDED = 'ended';

    public static function tryFromString(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }

    public function isTerminal(): bool
    {
        return $this === self::ENDED;
    }
}
