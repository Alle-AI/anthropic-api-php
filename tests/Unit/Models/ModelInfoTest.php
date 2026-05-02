<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Models;

use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Models\ModelFamily;
use AlleAI\Anthropic\Models\ModelInfo;
use AlleAI\Anthropic\Models\ModelList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelInfo::class)]
#[CoversClass(ModelList::class)]
final class ModelInfoTest extends TestCase
{
    public function testModelInfoFromArray(): void
    {
        $info = ModelInfo::fromArray([
            'id' => 'claude-sonnet-4-7',
            'type' => 'model',
            'display_name' => 'Claude Sonnet 4.7',
            'created_at' => '2026-04-01T00:00:00Z',
        ]);

        self::assertSame('claude-sonnet-4-7', $info->id);
        self::assertSame('Claude Sonnet 4.7', $info->displayName);
        $model = $info->toModel();
        self::assertInstanceOf(Model::class, $model);
        self::assertSame(ModelFamily::SONNET, $model->family);
    }

    public function testModelListPaging(): void
    {
        $list = ModelList::fromArray([
            'data' => [
                ['id' => 'claude-opus-4-7', 'type' => 'model'],
                ['id' => 'claude-sonnet-4-7', 'type' => 'model'],
            ],
            'has_more' => true,
            'first_id' => 'claude-opus-4-7',
            'last_id' => 'claude-sonnet-4-7',
        ]);

        self::assertCount(2, $list->data);
        self::assertTrue($list->hasMore);
        self::assertSame('claude-opus-4-7', $list->firstId);
    }
}
