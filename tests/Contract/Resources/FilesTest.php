<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Contract\Resources;

use AlleAI\Anthropic\Beta\BetaHeaders;
use AlleAI\Anthropic\Files\FileUpload;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Resources\Files;
use AlleAI\Anthropic\Tests\Support\Fixture;
use AlleAI\Anthropic\Tests\Support\TestClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Files::class)]
final class FilesTest extends TestCase
{
    public function testUploadSendsMultipartAndParsesMetadata(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('files/file.json'));

        $upload = new FileUpload(
            contents: 'pretend pdf bytes',
            filename: 'contract.pdf',
            mimeType: 'application/pdf',
        );
        $file = $client->files()->upload($upload);

        self::assertSame('file_01HGS4P9', $file->id);
        self::assertSame('contract.pdf', $file->filename);
        self::assertSame('application/pdf', $file->mimeType);
        self::assertSame(12345, $file->sizeBytes);

        $req = $http->lastRequest();
        self::assertSame('POST', $req->getMethod());
        self::assertSame('https://api.anthropic.com/v1/files', (string) $req->getUri());

        $contentType = $req->getHeaderLine(Headers::CONTENT_TYPE);
        self::assertStringStartsWith('multipart/form-data; boundary=', $contentType);

        $body = (string) $req->getBody();
        self::assertStringContainsString('Content-Disposition: form-data; name="file"; filename="contract.pdf"', $body);
        self::assertStringContainsString('Content-Type: application/pdf', $body);
        self::assertStringContainsString('pretend pdf bytes', $body);
    }

    public function testUploadAttachesFilesApiBetaHeader(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('files/file.json'));

        $client->files()->upload(new FileUpload(filename: 'x.txt', contents: 'x', mimeType: 'text/plain'));

        self::assertStringContainsString(
            BetaHeaders::FILES_API,
            $http->lastRequest()->getHeaderLine(Headers::ANTHROPIC_BETA),
        );
    }

    public function testGetReturnsFileResource(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('files/file.json'));

        $file = $client->files()->get('file_01HGS4P9');

        self::assertSame('file_01HGS4P9', $file->id);
        self::assertSame('GET', $http->lastRequest()->getMethod());
        self::assertSame(
            'https://api.anthropic.com/v1/files/file_01HGS4P9',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testListWithPaginationCursorsAddsQueryString(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('files/file_list.json'));

        $list = $client->files()->list(beforeId: null, afterId: 'file_01A', limit: 50);

        self::assertCount(2, $list->data);
        self::assertSame('file_01A', $list->firstId);
        self::assertSame('file_01B', $list->lastId);
        self::assertFalse($list->hasMore);

        $url = (string) $http->lastRequest()->getUri();
        self::assertStringContainsString('after_id=file_01A', $url);
        self::assertStringContainsString('limit=50', $url);
        self::assertStringNotContainsString('before_id', $url);
    }

    public function testListWithoutCursorsOmitsQueryString(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('files/file_list.json'));

        $client->files()->list();

        self::assertSame('https://api.anthropic.com/v1/files', (string) $http->lastRequest()->getUri());
    }

    public function testDeleteSendsDeleteRequest(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushRawResponse(204, '');

        $client->files()->delete('file_01HGS4P9');

        self::assertSame('DELETE', $http->lastRequest()->getMethod());
        self::assertSame(
            'https://api.anthropic.com/v1/files/file_01HGS4P9',
            (string) $http->lastRequest()->getUri(),
        );
    }

    public function testDownloadStreamsContentToSink(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushRawResponse(200, 'binary-payload-here');

        $sink = fopen('php://memory', 'wb+');
        self::assertNotFalse($sink);

        $client->files()->downloadTo('file_01HGS4P9', $sink);

        rewind($sink);
        self::assertSame('binary-payload-here', stream_get_contents($sink));
        fclose($sink);

        self::assertStringEndsWith('/v1/files/file_01HGS4P9/content', (string) $http->lastRequest()->getUri());
    }

    public function testFilenameWithQuotesIsEscaped(): void
    {
        [$client, $http] = TestClientFactory::make();
        $http->pushJsonResponse(200, Fixture::json('files/file.json'));

        $client->files()->upload(new FileUpload(
            filename: 'sneaky"name.txt',
            contents: 'x',
            mimeType: 'text/plain',
        ));

        $body = (string) $http->lastRequest()->getBody();
        self::assertStringContainsString('filename="sneaky\\"name.txt"', $body);
    }
}
