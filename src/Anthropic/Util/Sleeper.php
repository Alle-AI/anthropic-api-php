<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Util;

interface Sleeper
{
    public function sleep(float $seconds): void;
}
