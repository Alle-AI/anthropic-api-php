<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Util;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    public function microtime(): float
    {
        return microtime(true);
    }
}
