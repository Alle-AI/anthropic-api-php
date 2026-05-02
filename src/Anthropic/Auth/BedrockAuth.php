<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Auth;

use AlleAI\Anthropic\Util\Json;
use Aws\Credentials\CredentialProvider;
use Aws\Credentials\CredentialsInterface;
use Aws\Signature\SignatureV4;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * AuthProvider that targets the AWS Bedrock runtime instead of Anthropic's
 * direct API. Rewrites the URL, transforms the body to Bedrock's expected
 * shape, and signs the request with AWS SigV4.
 *
 * Requires `aws/aws-sdk-php` to be installed:
 *
 * ```bash
 * composer require aws/aws-sdk-php
 * ```
 *
 * Usage:
 *
 * ```php
 * $client = Client::builder()
 *     ->withAuth(BedrockAuth::fromEnvironment(region: 'us-east-1'))
 *     ->build();
 *
 * $client->messages()->create(
 *     model: 'anthropic.claude-sonnet-4-7-v1:0',  // Bedrock model id format
 *     maxTokens: 1024,
 *     messages: [['role' => 'user', 'content' => 'Hello']],
 * );
 * ```
 *
 * Use Anthropic-on-Bedrock model ids (e.g. `anthropic.claude-sonnet-4-7-v1:0`)
 * — Bedrock won't accept the bare `claude-sonnet-4-7` aliases.
 */
final class BedrockAuth implements RequestTransformingAuthProvider
{
    public const BEDROCK_ANTHROPIC_VERSION = 'bedrock-2023-05-31';

    private readonly UriFactoryInterface $uriFactory;
    private readonly StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly CredentialsInterface $credentials,
        private readonly string $region,
        ?UriFactoryInterface $uriFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->uriFactory = $uriFactory ?? Psr17FactoryDiscovery::findUriFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * Build a BedrockAuth using the AWS default credentials chain
     * (env vars, shared credentials file, IAM role, etc.).
     */
    public static function fromEnvironment(?string $region = null): self
    {
        if (!class_exists(CredentialProvider::class)) {
            throw new \RuntimeException(
                'BedrockAuth requires aws/aws-sdk-php. Install it with: composer require aws/aws-sdk-php',
            );
        }

        $promise = (CredentialProvider::defaultProvider())();
        if (!is_object($promise) || !method_exists($promise, 'wait')) {
            throw new \RuntimeException('Unexpected return from AWS credentials provider.');
        }
        /** @var CredentialsInterface $credentials */
        $credentials = $promise->wait();

        $envRegion = getenv('AWS_REGION');
        $defaultRegion = getenv('AWS_DEFAULT_REGION');
        $effectiveRegion = $region
            ?? (is_string($envRegion) && $envRegion !== '' ? $envRegion : null)
            ?? (is_string($defaultRegion) && $defaultRegion !== '' ? $defaultRegion : null)
            ?? 'us-east-1';

        return new self($credentials, $effectiveRegion);
    }

    public function authenticate(RequestInterface $request): array
    {
        // Not used — RequestTransformingAuthProvider takes the apply() path.
        return [];
    }

    public function baseUrl(?string $configured): string
    {
        // Force the Bedrock runtime endpoint regardless of configured base URL.
        return sprintf('https://bedrock-runtime.%s.amazonaws.com', $this->region);
    }

    public function apply(RequestInterface $request): RequestInterface
    {
        if (!class_exists(SignatureV4::class)) {
            throw new \RuntimeException(
                'BedrockAuth requires aws/aws-sdk-php. Install it with: composer require aws/aws-sdk-php',
            );
        }

        $body = (string) $request->getBody();
        $decoded = $body !== '' ? Json::decode($body) : [];

        $modelId = isset($decoded['model']) && is_string($decoded['model']) ? $decoded['model'] : '';
        if ($modelId === '') {
            throw new \InvalidArgumentException('BedrockAuth: request body is missing a model id.');
        }

        $isStream = isset($decoded['stream']) && $decoded['stream'] === true;
        $invokePath = $isStream ? 'invoke-with-response-stream' : 'invoke';

        // Bedrock expects anthropic_version in the body and the model id in the URL.
        unset($decoded['model'], $decoded['stream']);
        $decoded['anthropic_version'] = self::BEDROCK_ANTHROPIC_VERSION;

        $url = sprintf(
            'https://bedrock-runtime.%s.amazonaws.com/model/%s/%s',
            $this->region,
            rawurlencode($modelId),
            $invokePath,
        );

        // Strip Anthropic-only headers that don't belong on a Bedrock request.
        $rebuilt = $request
            ->withoutHeader('x-api-key')
            ->withoutHeader('anthropic-version')
            ->withoutHeader('anthropic-beta')
            ->withoutHeader('anthropic-idempotency-key')
            ->withMethod('POST')
            ->withUri($this->uriFactory->createUri($url))
            ->withHeader('content-type', 'application/json')
            ->withBody($this->streamFactory->createStream(Json::encode($decoded)));

        $signer = new SignatureV4('bedrock', $this->region);

        return $signer->signRequest($rebuilt, $this->credentials);
    }
}
