<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Files;

/**
 * Describes a file to be uploaded via the Files API. Pick the factory that
 * matches your input — local path, in-memory bytes, or any PSR-7 stream.
 */
final class FileUpload
{
    public function __construct(
        public readonly string $filename,
        public readonly string $contents,
        public readonly string $mimeType,
    ) {
    }

    public static function fromPath(string $path, ?string $mimeType = null): self
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('Cannot read file: %s', $path));
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException(sprintf('Failed to read file: %s', $path));
        }

        return new self(
            filename: basename($path),
            contents: $bytes,
            mimeType: $mimeType ?? self::guessMime($path, $bytes),
        );
    }

    public static function fromString(string $filename, string $contents, string $mimeType): self
    {
        return new self($filename, $contents, $mimeType);
    }

    private static function guessMime(string $path, string $bytes): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_buffer($finfo, $bytes);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'json' => 'application/json',
            'csv' => 'text/csv',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
