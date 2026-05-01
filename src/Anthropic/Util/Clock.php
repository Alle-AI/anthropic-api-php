<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Util;

interface Clock
{
    public function now(): \DateTimeImmutable;

    public function microtime(): float;
}
