<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Tools;

use AlleAI\Anthropic\Tools\Schema\Attributes\Enum;
use AlleAI\Anthropic\Tools\Schema\Attributes\Param;
use AlleAI\Anthropic\Tools\Schema\SchemaGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaGenerator::class)]
final class SchemaGeneratorTest extends TestCase
{
    public function testReflectsScalarTypes(): void
    {
        $schema = SchemaGenerator::fromMethod(SchemaFixtureA::class, 'run');
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'City name'],
                'count' => ['type' => 'integer'],
                'price' => ['type' => 'number'],
                'urgent' => ['type' => 'boolean'],
            ],
            'required' => ['city', 'count', 'price', 'urgent'],
        ], $schema);
    }

    public function testNullableMarksField(): void
    {
        $schema = SchemaGenerator::fromMethod(SchemaFixtureNullable::class, 'run');
        self::assertIsArray($schema['properties']);
        self::assertIsArray($schema['properties']['note']);
        self::assertTrue($schema['properties']['note']['nullable']);
    }

    public function testDefaultsRemoveFromRequired(): void
    {
        $schema = SchemaGenerator::fromMethod(SchemaFixtureDefault::class, 'run');
        self::assertSame(['city'], $schema['required']);
        self::assertIsArray($schema['properties']);
        self::assertArrayHasKey('units', $schema['properties']);
    }

    public function testEnumAttributeAddsEnum(): void
    {
        $schema = SchemaGenerator::fromMethod(SchemaFixtureEnum::class, 'run');
        self::assertIsArray($schema['properties']);
        self::assertIsArray($schema['properties']['units']);
        self::assertSame(['c', 'f'], $schema['properties']['units']['enum']);
    }

    public function testBackedEnumDetected(): void
    {
        $schema = SchemaGenerator::fromMethod(SchemaFixtureBackedEnum::class, 'run');
        self::assertIsArray($schema['properties']);
        self::assertIsArray($schema['properties']['status']);
        self::assertSame('string', $schema['properties']['status']['type']);
        self::assertSame(['ok', 'fail'], $schema['properties']['status']['enum']);
    }
}

final class SchemaFixtureA
{
    public function run(
        #[Param('City name')]
        string $city,
        int $count,
        float $price,
        bool $urgent,
    ): void {
    }
}

final class SchemaFixtureNullable
{
    public function run(?string $note): void
    {
    }
}

final class SchemaFixtureDefault
{
    public function run(string $city, string $units = 'c'): void
    {
    }
}

final class SchemaFixtureEnum
{
    public function run(
        #[Enum('c', 'f')]
        string $units,
    ): void {
    }
}

enum FixtureStatus: string
{
    case OK = 'ok';
    case FAIL = 'fail';
}

final class SchemaFixtureBackedEnum
{
    public function run(FixtureStatus $status): void
    {
    }
}
