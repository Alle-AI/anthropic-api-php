<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Batches;

use AlleAI\Anthropic\Batches\BatchResult;
use AlleAI\Anthropic\Batches\BatchStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BatchResult::class)]
#[CoversClass(BatchStatus::class)]
final class BatchResultTest extends TestCase
{
    public function testParsesSucceededResult(): void
    {
        $result = BatchResult::fromArray([
            'custom_id' => 'row-1',
            'result' => [
                'type' => 'succeeded',
                'message' => [
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [['type' => 'text', 'text' => 'positive']],
                    'model' => 'claude-haiku-4-5',
                    'stop_reason' => 'end_turn',
                    'stop_sequence' => null,
                    'usage' => ['input_tokens' => 5, 'output_tokens' => 1],
                ],
            ],
        ]);

        self::assertTrue($result->succeeded());
        self::assertSame('row-1', $result->customId);
        self::assertSame('positive', $result->message?->text());
    }

    public function testParsesErroredResult(): void
    {
        $result = BatchResult::fromArray([
            'custom_id' => 'row-2',
            'result' => [
                'type' => 'errored',
                'error' => ['type' => 'invalid_request_error', 'message' => 'bad'],
            ],
        ]);

        self::assertFalse($result->succeeded());
        self::assertSame('errored', $result->resultType);
        self::assertSame('bad', $result->errorPayload['message'] ?? null);
    }

    public function testStatusEnumIsTerminal(): void
    {
        self::assertTrue(BatchStatus::ENDED->isTerminal());
        self::assertFalse(BatchStatus::IN_PROGRESS->isTerminal());
        self::assertFalse(BatchStatus::CANCELING->isTerminal());
    }
}
