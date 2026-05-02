<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Http;

use AlleAI\Anthropic\Http\RetryPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryPolicy::class)]
final class RetryPolicyTest extends TestCase
{
    public function testDefaultRetryableStatusesIncludeStandardSet(): void
    {
        $policy = new RetryPolicy();
        foreach ([408, 409, 429, 500, 502, 503, 504, 529] as $status) {
            self::assertTrue($policy->isRetryableStatus($status), "status $status should be retryable");
        }
        self::assertFalse($policy->isRetryableStatus(200));
        self::assertFalse($policy->isRetryableStatus(400));
        self::assertFalse($policy->isRetryableStatus(401));
    }

    public function testDelayGrowsExponentiallyWithoutJitter(): void
    {
        $policy = new RetryPolicy(baseDelay: 1.0, maxDelay: 100.0, jitter: 0.0);
        self::assertEqualsWithDelta(1.0, $policy->delayFor(1), 0.0001);
        self::assertEqualsWithDelta(2.0, $policy->delayFor(2), 0.0001);
        self::assertEqualsWithDelta(4.0, $policy->delayFor(3), 0.0001);
        self::assertEqualsWithDelta(8.0, $policy->delayFor(4), 0.0001);
    }

    public function testDelayCapsAtMaxDelay(): void
    {
        $policy = new RetryPolicy(baseDelay: 1.0, maxDelay: 5.0, jitter: 0.0);
        self::assertEqualsWithDelta(5.0, $policy->delayFor(10), 0.0001);
    }

    public function testRetryAfterOverridesExponential(): void
    {
        $policy = new RetryPolicy(baseDelay: 1.0, maxDelay: 100.0, jitter: 0.0);
        self::assertEqualsWithDelta(7.0, $policy->delayFor(1, retryAfterSeconds: 7), 0.0001);
    }

    public function testRetryAfterIsCappedByMaxDelay(): void
    {
        $policy = new RetryPolicy(baseDelay: 1.0, maxDelay: 30.0, jitter: 0.0);
        self::assertEqualsWithDelta(30.0, $policy->delayFor(1, retryAfterSeconds: 1000), 0.0001);
    }

    public function testRetryAfterIgnoredWhenHonorRetryAfterIsFalse(): void
    {
        $policy = new RetryPolicy(baseDelay: 2.0, maxDelay: 100.0, jitter: 0.0, honorRetryAfter: false);
        self::assertEqualsWithDelta(2.0, $policy->delayFor(1, retryAfterSeconds: 50), 0.0001);
    }

    public function testJitterStaysWithinBounds(): void
    {
        $policy = new RetryPolicy(baseDelay: 1.0, maxDelay: 100.0, jitter: 0.5);
        for ($i = 0; $i < 100; $i++) {
            $delay = $policy->delayFor(2);
            // base = 2 seconds; jitter ±50% → [1, 3]
            self::assertGreaterThanOrEqual(0.0, $delay);
            self::assertLessThanOrEqual(3.5, $delay);
        }
    }

    public function testDisabledHasMaxAttemptsOne(): void
    {
        self::assertSame(1, RetryPolicy::disabled()->maxAttempts);
    }
}
