<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

use Psr\Http\Message\StreamInterface;

/**
 * Read-only PSR-7 stream backing {@see MinimalResponse}. The body is
 * always a complete in-memory string — no underlying handle.
 *
 * @internal
 */
final class MinimalStream implements StreamInterface
{
    private int $offset = 0;

    public function __construct(private readonly string $contents) {}

    public function __toString(): string
    {
        return $this->contents;
    }

    public function close(): void
    {
        // no-op
    }

    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->contents);
    }

    public function tell(): int
    {
        return $this->offset;
    }

    public function eof(): bool
    {
        return $this->offset >= strlen($this->contents);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $size = strlen($this->contents);
        $this->offset = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->offset + $offset,
            SEEK_END => $size + $offset,
            default => throw new \InvalidArgumentException('Invalid $whence.'),
        };

        if ($this->offset < 0 || $this->offset > $size) {
            $this->offset = max(0, min($this->offset, $size));
        }
    }

    public function rewind(): void
    {
        $this->offset = 0;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new \RuntimeException('MinimalStream is read-only.');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        if ($length <= 0 || $this->eof()) {
            return '';
        }

        $chunk = substr($this->contents, $this->offset, $length);
        $this->offset += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        $remaining = substr($this->contents, $this->offset);
        $this->offset = strlen($this->contents);

        return $remaining;
    }

    /**
     * @param  string|null  $key
     */
    public function getMetadata($key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
