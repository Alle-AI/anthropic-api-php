<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Support;

/**
 * Minimal loader for JSON / SSE fixtures under tests/Fixtures.
 */
final class Fixture
{
    public static function path(string $relative): string
    {
        $path = __DIR__ . '/../Fixtures/' . ltrim($relative, '/');
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Fixture not found: %s', $relative));
        }

        return $path;
    }

    public static function raw(string $relative): string
    {
        $path = self::path($relative);
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException(sprintf('Failed to read fixture: %s', $relative));
        }

        return $data;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function json(string $relative): array
    {
        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode(self::raw($relative), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
