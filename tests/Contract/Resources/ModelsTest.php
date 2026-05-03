<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Contract\Resources;

use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Models\Model;
use AlleAI\Anthropic\Models\ModelFamily;
use AlleAI\Anthropic\Resources\Models;
use AlleAI\Anthropic\Tests\Support\Fixture;
use AlleAI\Anthropic\Tests\Support\TestClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Models::class)]
final class ModelsTest extends TestCase
{
    public function testListReturnsModelInfoEntries(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('models/model_list.json'));

        $list = $client->models()->list();

        self::assertCount(3, $list->data);
        self::assertSame('claude-opus-4-7', $list->data[0]->id);
        self::assertSame('Claude Opus 4.7', $list->data[0]->displayName);
        self::assertFalse($list->hasMore);

        self::assertSame('GET', $http->lastRequest()->getMethod());
        self::assertSame('https://api.anthropic.com/v1/models', (string) $http->lastRequest()->getUri());
        self::assertSame('application/json', $http->lastRequest()->getHeaderLine(Headers::ACCEPT));
    }

    public function testListWithCursorsAddsQuery(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('models/model_list.json'));

        $client->models()->list(beforeId: 'claude-opus-4-7', afterId: 'claude-haiku-4-5', limit: 25);

        $url = (string) $http->lastRequest()->getUri();
        self::assertStringContainsString('before_id=claude-opus-4-7', $url);
        self::assertStringContainsString('after_id=claude-haiku-4-5', $url);
        self::assertStringContainsString('limit=25', $url);
    }

    public function testGetReturnsSingleModel(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('models/model.json'));

        $info = $client->models()->get(Model::CLAUDE_SONNET_4_7);

        self::assertSame('claude-sonnet-4-7', $info->id);
        self::assertSame('Claude Sonnet 4.7', $info->displayName);

        self::assertSame(
            'https://api.anthropic.com/v1/models/claude-sonnet-4-7',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testToModelBridgesBackToValueObject(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('models/model.json'));

        $model = $client->models()->get(Model::CLAUDE_SONNET_4_7)->toModel();

        self::assertSame('claude-sonnet-4-7', (string) $model);
        self::assertSame(ModelFamily::SONNET, $model->family);
    }
}
