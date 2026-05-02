<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Files;

use AlleAI\Anthropic\Files\FileUpload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileUpload::class)]
final class FileUploadTest extends TestCase
{
    public function testFromString(): void
    {
        $upload = FileUpload::fromString('hi.txt', 'hello', 'text/plain');
        self::assertSame('hi.txt', $upload->filename);
        self::assertSame('hello', $upload->contents);
        self::assertSame('text/plain', $upload->mimeType);
    }

    public function testFromPathReadsFileAndDetectsMime(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upload_') . '.txt';
        file_put_contents($tmp, 'sample contents');
        try {
            $upload = FileUpload::fromPath($tmp);
            self::assertSame('sample contents', $upload->contents);
            self::assertStringContainsString('text/plain', $upload->mimeType);
        } finally {
            @unlink($tmp);
        }
    }

    public function testFromPathThrowsWhenMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FileUpload::fromPath('/nonexistent/__never__.bin');
    }
}
