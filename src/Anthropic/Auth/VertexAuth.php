<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Auth;

use AlleAI\Anthropic\Util\Json;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\FetchAuthTokenInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * AuthProvider that targets Google Cloud Vertex AI's hosted Claude
 * endpoints. Acquires an OAuth access token via Google's Application
 * Default Credentials (ADC), rewrites the URL to the Vertex format,
 * and transforms the request body so Vertex accepts it.
 *
 * Requires `google/auth` to be installed:
 *
 * ```bash
 * composer require google/auth
 * ```
 *
 * Usage:
 *
 * ```php
 * $client = Client::builder()
 *     ->withAuth(VertexAuth::fromEnvironment(
 *         projectId: 'my-gcp-project',
 *         region: 'us-east5',
 *     ))
 *     ->build();
 *
 * $client->messages()->create(
 *     model: 'claude-sonnet-4-7@20260101',  // Vertex model id format
 *     maxTokens: 1024,
 *     messages: [['role' => 'user', 'content' => 'Hello']],
 * );
 * ```
 *
 * Use the Vertex publisher model ids (e.g. `claude-sonnet-4-7@20260101`).
 */
final class VertexAuth implements RequestTransformingAuthProvider
{
    public const VERTEX_ANTHROPIC_VERSION = 'vertex-2023-10-16';

    private const SCOPES = ['https://www.googleapis.com/auth/cloud-platform'];

    private readonly UriFactoryInterface $uriFactory;
    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly FetchAuthTokenInterface $tokenFetcher,
        private readonly string $projectId,
        private readonly string $region,
        ?UriFactoryInterface $uriFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUriFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * Build a VertexAuth using Google ADC. Pass `$projectId` explicitly or
     * set the `GOOGLE_CLOUD_PROJECT` env var; same for region with
     * `GOOGLE_CLOUD_REGION`.
     */
    public static function fromEnvironment(?string $projectId = null, ?string $region = null): self
    {
        if (!class_exists(ApplicationDefaultCredentials::class)) {
            throw new \RuntimeException(
                'VertexAuth requires google/auth. Install it with: composer require google/auth',
            );
        }

        $envProject = getenv('GOOGLE_CLOUD_PROJECT');
        $envRegion = getenv('GOOGLE_CLOUD_REGION');
        $effectiveProject = $projectId ?? (is_string($envProject) && $envProject !== '' ? $envProject : null);
        $effectiveRegion = $region
            ?? (is_string($envRegion) && $envRegion !== '' ? $envRegion : null)
            ?? 'us-east5';

        if ($effectiveProject === null) {
            throw new \InvalidArgumentException(
                'VertexAuth requires a project id — pass it explicitly or set GOOGLE_CLOUD_PROJECT.',
            );
        }

        $tokenFetcher = ApplicationDefaultCredentials::getCredentials(self::SCOPES);

        return new self($tokenFetcher, $effectiveProject, $effectiveRegion);
    }

    public function authenticate(RequestInterface $request): array
    {
        return [];
    }

    public function baseUrl(?string $configured): string
    {
        return $this->vertexHost();
    }

    public function apply(RequestInterface $request): RequestInterface
    {
        $body = (string) $request->getBody();
        $decoded = $body !== '' ? Json::decode($body) : [];

        $modelId = isset($decoded['model']) && is_string($decoded['model']) ? $decoded['model'] : '';
        if ($modelId === '') {
            throw new \InvalidArgumentException('VertexAuth: request body is missing a model id.');
        }

        $isStream = isset($decoded['stream']) && $decoded['stream'] === true;
        $rpc = $isStream ? 'streamRawPredict' : 'rawPredict';

        unset($decoded['model'], $decoded['stream']);
        $decoded['anthropic_version'] = self::VERTEX_ANTHROPIC_VERSION;

        $url = sprintf(
            '%s/v1/projects/%s/locations/%s/publishers/anthropic/models/%s:%s',
            $this->vertexHost(),
            rawurlencode($this->projectId),
            rawurlencode($this->region),
            rawurlencode($modelId),
            $rpc,
        );

        $token = $this->fetchToken();

        return $request
            ->withoutHeader('x-api-key')
            ->withoutHeader('anthropic-version')
            ->withoutHeader('anthropic-beta')
            ->withoutHeader('anthropic-idempotency-key')
            ->withMethod('POST')
            ->withUri($this->uriFactory->createUri($url))
            ->withHeader('content-type', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withBody($this->streamFactory->createStream(Json::encode($decoded)));
    }

    private function fetchToken(): string
    {
        /** @var array<string, mixed> $authResult */
        $authResult = $this->tokenFetcher->fetchAuthToken();
        $token = $authResult['access_token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('VertexAuth: token fetcher did not return an access_token.');
        }

        return $token;
    }

    private function vertexHost(): string
    {
        // Vertex regional endpoints; "global" uses the unprefixed host.
        if ($this->region === 'global') {
            return 'https://aiplatform.googleapis.com';
        }

        return sprintf('https://%s-aiplatform.googleapis.com', $this->region);
    }
}
