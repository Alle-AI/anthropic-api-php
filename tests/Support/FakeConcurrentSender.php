<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Support;

use AlleAI\Anthropic\Http\ConcurrentResult;
use AlleAI\Anthropic\Http\ConcurrentSender;
use Psr\Http\Message\RequestInterface;

/**
 * Test double for {@see ConcurrentSender} — captures all received
 * requests and replays a programmed list of {@see ConcurrentResult}s
 * back to the caller in order.
 */
final class FakeConcurrentSender implements ConcurrentSender
{
    /** @var list<RequestInterface> */
    public array $captured = [];

    /** @var list<ConcurrentResult> */
    private array $programmed = [];

    public ?int $lastConcurrency = null;

    public function pushSuccess(int $status, string $body): self
    {
        $this->programmed[] = new ConcurrentResult($status, $body, null);

        return $this;
    }

    public function pushSuccessJson(int $status, mixed $payload): self
    {
        return $this->pushSuccess($status, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function pushError(\AlleAI\Anthropic\Exceptions\AnthropicException $exception): self
    {
        if ($exception instanceof \AlleAI\Anthropic\Exceptions\ApiException
            || $exception instanceof \AlleAI\Anthropic\Exceptions\ConnectionException
            || $exception instanceof \AlleAI\Anthropic\Exceptions\TimeoutException
        ) {
            $this->programmed[] = new ConcurrentResult(null, null, $exception);

            return $this;
        }

        throw new \LogicException('FakeConcurrentSender: only ApiException / Connection / Timeout are programmable.');
    }

    public function sendAll(array $requests, int $concurrency = 5): array
    {
        $this->captured = array_merge($this->captured, $requests);
        $this->lastConcurrency = $concurrency;

        if (count($this->programmed) < count($requests)) {
            throw new \LogicException(sprintf(
                'FakeConcurrentSender: %d programmed result(s), %d request(s) received.',
                count($this->programmed),
                count($requests),
            ));
        }

        /** @var list<ConcurrentResult> $out */
        $out = [];
        foreach ($requests as $_) {
            $entry = array_shift($this->programmed);
            if ($entry === null) {
                throw new \LogicException('FakeConcurrentSender: programmed queue exhausted.');
            }
            $out[] = $entry;
        }

        return $out;
    }
}
