<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Util;

use AlleAI\Anthropic\Exceptions\AnthropicException;
use AlleAI\Anthropic\Util\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    public function testEncodeAndDecodeRoundTripsAssociativeArrays(): void
    {
        $value = ['a' => 1, 'b' => ['nested' => true], 'c' => null];
        $encoded = Json::encode($value);

        self::assertSame($value, Json::decode($encoded));
    }

    public function testEncodeDoesNotEscapeSlashes(): void
    {
        $encoded = Json::encode(['url' => 'https://example.com/foo']);

        self::assertStringContainsString('https://example.com/foo', $encoded);
    }

    public function testEncodeKeepsUnicodeUnescaped(): void
    {
        $encoded = Json::encode(['city' => 'Köln']);

        self::assertStringContainsString('Köln', $encoded);
    }

    public function testDecodeThrowsOnInvalidJson(): void
    {
        $this->expectException(AnthropicException::class);
        $this->expectExceptionMessage('Failed to decode JSON');

        Json::decode('{"unterminated');
    }

    public function testDecodeLineReturnsNullForEmptyLine(): void
    {
        self::assertNull(Json::decodeLine(''));
        self::assertNull(Json::decodeLine('   '));
    }

    public function testDecodeLineParsesValidLine(): void
    {
        self::assertSame(['x' => 1], Json::decodeLine('  {"x":1}  '));
    }
}
