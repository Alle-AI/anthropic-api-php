<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages\Content;

/**
 * Image content block. Supply either a URL or base64 data — not both.
 */
final readonly class ImageBlock implements ContentBlock
{
    /**
     * @param  'url'|'base64'  $sourceType
     */
    public function __construct(
        public string $sourceType,
        public string $data,
        public ?string $mediaType = null,
        public ?CacheControl $cacheControl = null,
    ) {
    }

    public static function fromUrl(string $url, ?CacheControl $cacheControl = null): self
    {
        return new self(sourceType: 'url', data: $url, mediaType: null, cacheControl: $cacheControl);
    }

    /**
     * Read a local file and encode it as base64.
     */
    public static function fromFile(string $path, ?CacheControl $cacheControl = null): self
    {
        if (!is_readable($path)) {
            throw new \InvalidArgumentException(sprintf('Cannot read image file: %s', $path));
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new \RuntimeException(sprintf('Failed to read image file: %s', $path));
        }

        $mediaType = self::detectMediaType($path, $bytes);

        return new self(
            sourceType: 'base64',
            data: base64_encode($bytes),
            mediaType: $mediaType,
            cacheControl: $cacheControl,
        );
    }

    public static function fromBase64(string $base64, string $mediaType, ?CacheControl $cacheControl = null): self
    {
        return new self('base64', $base64, $mediaType, $cacheControl);
    }

    public function type(): string
    {
        return 'image';
    }

    public function toArray(): array
    {
        $source = match ($this->sourceType) {
            'url' => ['type' => 'url', 'url' => $this->data],
            'base64' => [
                'type' => 'base64',
                'media_type' => $this->mediaType ?? 'image/png',
                'data' => $this->data,
            ],
        };

        $out = ['type' => 'image', 'source' => $source];
        if ($this->cacheControl !== null) {
            $out['cache_control'] = $this->cacheControl->toArray();
        }

        return $out;
    }

    private static function detectMediaType(string $path, string $bytes): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_buffer($finfo, $bytes);
                finfo_close($finfo);
                if (is_string($mime) && str_starts_with($mime, 'image/')) {
                    return $mime;
                }
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
