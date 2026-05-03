<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Support;

use AlleAI\Anthropic\Util\Sleeper;

/**
 * Test {@see Sleeper} that records every requested duration instead of
 * actually sleeping. Lets retry / poll tests run instantly while still
 * asserting the back-off / interval timing.
 */
final class RecordingSleeper implements Sleeper
{
    /** @var list<float> */
    public array $durations = [];

    public function sleep(float $seconds): void
    {
        $this->durations[] = $seconds;
    }
}
