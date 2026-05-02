<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Resources;

use AlleAI\Anthropic\ClientOptions;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\Transport;
use AlleAI\Anthropic\Models\ModelInfo;
use AlleAI\Anthropic\Models\ModelList;
use AlleAI\Anthropic\Util\Json;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Resource for `GET /v1/models` and `GET /v1/models/{id}`.
 *
 * ```php
 * foreach ($client->models()->list()->data as $model) {
 *     echo $model->id, "  ", $model->displayName, "\n";
 * }
 *
 * $info = $client->models()->get(Model::CLAUDE_SONNET_4_7);
 * ```
 */
final class Models
{
    public function __construct(
        private readonly Transport $transport,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly ClientOptions $options,
    ) {
    }

    public function list(?string $beforeId = null, ?string $afterId = null, ?int $limit = null): ModelList
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

        $url = $this->endpoint('/v1/models');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $request = $this->withDefaultHeaders($this->requestFactory->createRequest('GET', $url));
        $response = $this->transport->sendRequest($request);

        return ModelList::fromArray(Json::decode((string) $response->getBody()));
    }

    public function get(string $modelId): ModelInfo
    {
        $request = $this->withDefaultHeaders(
            $this->requestFactory->createRequest('GET', $this->endpoint('/v1/models/' . rawurlencode($modelId))),
        );
        $response = $this->transport->sendRequest($request);

        return ModelInfo::fromArray(Json::decode((string) $response->getBody()));
    }

    private function withDefaultHeaders(RequestInterface $request): RequestInterface
    {
        $request = $request
            ->withHeader(Headers::ACCEPT, 'application/json')
            ->withHeader(Headers::ANTHROPIC_VERSION, $this->options->anthropicVersion);

        if ($this->options->anthropicBeta !== []) {
            $request = $request->withHeader(Headers::ANTHROPIC_BETA, implode(',', $this->options->anthropicBeta));
        }

        return $request;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->options->baseUrl, '/') . $path;
    }
}
