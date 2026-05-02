<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Models;

use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Models\ModelFamily;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Model::class)]
#[CoversClass(ModelFamily::class)]
final class ModelTest extends TestCase
{
    public function testOfAcceptsArbitraryId(): void
    {
        $model = Model::of('claude-sonnet-9-9-20300101');
        self::assertSame('claude-sonnet-9-9-20300101', (string) $model);
        self::assertSame(ModelFamily::SONNET, $model->family);
    }

    public function testRejectsEmptyId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Model::of('   ');
    }

    /**
     * @return iterable<string, array{string, ModelFamily}>
     */
    public static function familyExamples(): iterable
    {
        yield 'opus alias' => ['claude-opus-4-7', ModelFamily::OPUS];
        yield 'sonnet alias' => ['claude-sonnet-4-7', ModelFamily::SONNET];
        yield 'haiku alias' => ['claude-haiku-4-5', ModelFamily::HAIKU];
        yield 'unknown line' => ['claude-some-future-thing', ModelFamily::UNKNOWN];
    }

    #[DataProvider('familyExamples')]
    public function testFamilyDetection(string $id, ModelFamily $expected): void
    {
        self::assertSame($expected, Model::of($id)->family);
    }

    public function testConstantsAreLiveStrings(): void
    {
        self::assertSame('claude-sonnet-4-7', Model::CLAUDE_SONNET_4_7);
        self::assertSame('claude-opus-4-7', Model::CLAUDE_OPUS_4_7);
    }
}
