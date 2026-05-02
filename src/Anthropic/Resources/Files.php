<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Resources;

use AlleAI\Anthropic\Beta\BetaHeaders;
use AlleAI\Anthropic\ClientOptions;
use AlleAI\Anthropic\Files\FileList;
use AlleAI\Anthropic\Files\FileResource;
use AlleAI\Anthropic\Files\FileUpload;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\Transport;
use AlleAI\Anthropic\Util\Json;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Resource for the Files API (`POST /v1/files`, `GET /v1/files/{id}`,
 * `GET /v1/files`, `DELETE /v1/files/{id}`, `GET /v1/files/{id}/content`).
 *
 * Files API is a beta feature — calls automatically attach
 * {@see BetaHeaders::FILES_API} to the `anthropic-beta` header so callers
 * don't have to remember.
 *
 * ```php
 * $file = $client->files()->upload(FileUpload::fromPath('/contracts/q1.pdf'));
 *
 * $client->messages()->create(
 *     model: Model::CLAUDE_SONNET_4_7,
 *     maxTokens: 1024,
 *     messages: [[
 *         'role' => 'user',
 *         'content' => [
 *             ['type' => 'document', 'source' => ['type' => 'file', 'file_id' => $file->id]],
 *             ['type' => 'text', 'text' => 'Summarize section 4.'],
 *         ],
 *     ]],
 * );
 * ```
 */
final class Files
{
    public function __construct(
        private readonly Transport $transport,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ClientOptions $options,
    ) {
    }

    /**
     * Upload a file. Returns the metadata; the file content lives on
     * Anthropic's servers and is referenced by `file_id` in subsequent
     * Messages API calls.
     */
    public function upload(FileUpload $file): FileResource
    {
        $boundary = '----alleai-' . bin2hex(random_bytes(8));
        $body = $this->buildMultipartBody($file, $boundary);

        $request = $this->requestFactory->createRequest('POST', $this->endpoint('/v1/files'));
        $request = $this->withDefaultHeaders($request, includeJsonContentType: false)
            ->withHeader(Headers::CONTENT_TYPE, 'multipart/form-data; boundary=' . $boundary)
            ->withBody($this->streamFactory->createStream($body));

        $response = $this->transport->sendRequest($request);

        return FileResource::fromArray(Json::decode((string) $response->getBody()));
    }

    public function get(string $fileId): FileResource
    {
        $request = $this->requestFactory->createRequest('GET', $this->endpoint('/v1/files/' . rawurlencode($fileId)));
        $request = $this->withDefaultHeaders($request);
        $response = $this->transport->sendRequest($request);

        return FileResource::fromArray(Json::decode((string) $response->getBody()));
    }

    /**
     * List uploaded files. Pass cursors from a previous response for pagination.
     */
    public function list(?string $beforeId = null, ?string $afterId = null, ?int $limit = null): FileList
    {
        $query = [];
        if ($beforeId !== null) {
            $query['before_id'] = $beforeId;
        }
        if ($afterId !== null) {
            $query['after_id'] = $afterId;
        }
        if ($limit !== null) {
            $query['limit'] = (string) $limit;
        }

        $url = $this->endpoint('/v1/files');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $request = $this->withDefaultHeaders($this->requestFactory->createRequest('GET', $url));
        $response = $this->transport->sendRequest($request);

        return FileList::fromArray(Json::decode((string) $response->getBody()));
    }

    public function delete(string $fileId): void
    {
        $request = $this->withDefaultHeaders(
            $this->requestFactory->createRequest('DELETE', $this->endpoint('/v1/files/' . rawurlencode($fileId))),
        );
        $this->transport->sendRequest($request);
    }

    /**
     * Stream the raw bytes of a file back to disk. Anthropic only allows
     * downloading files created during certain workflows (e.g. computer-use
     * outputs); user uploads are typically not downloadable.
     *
     * @param  resource  $sink  open writable stream (e.g. fopen('out.pdf', 'wb'))
     */
    public function downloadTo(string $fileId, $sink): void
    {
        if (!is_resource($sink)) {
            throw new \InvalidArgumentException('$sink must be an open writable stream resource.');
        }

        $request = $this->withDefaultHeaders(
            $this->requestFactory->createRequest('GET', $this->endpoint('/v1/files/' . rawurlencode($fileId) . '/content')),
        );
        $response = $this->transport->sendRequest($request);

        $body = $response->getBody();
        $body->rewind();
        while (!$body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                break;
            }
            fwrite($sink, $chunk);
        }
    }

    private function buildMultipartBody(FileUpload $file, string $boundary): string
    {
        $eol = "\r\n";
        $body = '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="file"; filename="' . self::escapeFilename($file->filename) . '"' . $eol;
        $body .= 'Content-Type: ' . $file->mimeType . $eol . $eol;
        $body .= $file->contents . $eol;
        $body .= '--' . $boundary . '--' . $eol;

        return $body;
    }

    private static function escapeFilename(string $filename): string
    {
        return str_replace(['"', "\r", "\n"], ['\\"', '', ''], $filename);
    }

    private function withDefaultHeaders(RequestInterface $request, bool $includeJsonContentType = true): RequestInterface
    {
        $request = $request
            ->withHeader(Headers::ACCEPT, 'application/json')
            ->withHeader(Headers::ANTHROPIC_VERSION, $this->options->anthropicVersion);

        if ($includeJsonContentType) {
            $request = $request->withHeader(Headers::CONTENT_TYPE, 'application/json');
        }

        $beta = $this->options->anthropicBeta;
        if (!in_array(BetaHeaders::FILES_API, $beta, true)) {
            $beta[] = BetaHeaders::FILES_API;
        }
        $request = $request->withHeader(Headers::ANTHROPIC_BETA, implode(',', $beta));

        return $request;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->options->baseUrl, '/') . $path;
    }
}
