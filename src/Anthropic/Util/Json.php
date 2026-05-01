<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Util;

use AlleAI\Anthropic\Exceptions\AnthropicException;
use JsonException;

/**
 * Safe JSON encode/decode that throws on failure.
 */
final class Json
{
    /**
     * @param  array<array-key, mixed>|object  $value
     */
    public static function encode(array|object $value, int $flags = 0): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | $flags,
            );
        } catch (JsonException $e) {
            throw new AnthropicException('Failed to encode value as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function decode(string $json): array
    {
        try {
            /** @var array<array-key, mixed> $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AnthropicException('Failed to decode JSON response: ' . $e->getMessage(), 0, $e);
        }

        return $decoded;
    }

    /**
     * Decode a single line of JSON (useful for SSE/JSONL streams).
     *
     * @return array<array-key, mixed>|null  null if the line is empty
     */
    public static function decodeLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        return self::decode($line);
    }
}
