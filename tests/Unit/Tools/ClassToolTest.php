<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Tools;

use AlleAI\Anthropic\Tools\ClassTool;
use AlleAI\Anthropic\Tools\Schema\Attributes\Enum;
use AlleAI\Anthropic\Tools\Schema\Attributes\Param;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassTool::class)]
final class ClassToolTest extends TestCase
{
    public function testInputSchemaIsReflected(): void
    {
        $tool = new GetWeatherFixture();
        $schema = $tool->inputSchema();
        self::assertSame('object', $schema['type']);
        self::assertSame(['city'], $schema['required']);
        self::assertArrayHasKey('units', $schema['properties']);
        self::assertSame(['c', 'f'], $schema['properties']['units']['enum']);
    }

    public function testRunDispatchesToRunToolWithDefaults(): void
    {
        $tool = new GetWeatherFixture();
        self::assertSame(['city' => 'Accra', 'units' => 'c'], $tool->run(['city' => 'Accra']));
    }

    public function testRunCoercesStringToInt(): void
    {
        $tool = new CountFixture();
        self::assertSame(['count' => 5], $tool->run(['count' => '5']));
    }

    public function testRunRespectsExplicitInput(): void
    {
        $tool = new GetWeatherFixture();
        self::assertSame(['city' => 'Tokyo', 'units' => 'f'], $tool->run(['city' => 'Tokyo', 'units' => 'f']));
    }

    public function testRunThrowsWhenRequiredFieldMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new GetWeatherFixture())->run([]);
    }

    public function testMissingRunToolMethodThrowsClearly(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not declare a runTool() method');
        (new BrokenToolFixture())->inputSchema();
    }
}

final class GetWeatherFixture extends ClassTool
{
    public function name(): string
    {
        return 'get_weather';
    }

    public function description(): string
    {
        return 'Weather';
    }

    /**
     * @return array<string, mixed>
     */
    protected function runTool(
        #[Param('City')]
        string $city,
        #[Enum('c', 'f')]
        string $units = 'c',
    ): array {
        return ['city' => $city, 'units' => $units];
    }
}

final class CountFixture extends ClassTool
{
    public function name(): string
    {
        return 'count';
    }

    public function description(): string
    {
        return 'Count';
    }

    /**
     * @return array<string, mixed>
     */
    protected function runTool(int $count): array
    {
        return ['count' => $count];
    }
}

final class BrokenToolFixture extends ClassTool
{
    public function name(): string
    {
        return 'broken';
    }

    public function description(): string
    {
        return 'Missing runTool';
    }
}
