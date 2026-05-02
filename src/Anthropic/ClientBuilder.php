<?php

declare(strict_types=1);

namespace AlleAI\Anthropic;

use AlleAI\Anthropic\Auth\ApiKeyAuth;
use AlleAI\Anthropic\Auth\AuthProvider;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\RetryPolicy;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Fluent builder for {@see Client}. Use it when you need to inject a
 * specific PSR-18 client, configure retries, set beta flags, etc.
 *
 * ```php
 * $client = Client::builder()
 *     ->withApiKey(getenv('ANTHROPIC_API_KEY'))
 *     ->withAnthropicBeta('mcp-client-2025-04-04')
 *     ->withTimeout(120.0)
 *     ->build();
 * ```
 */
final class ClientBuilder
{
    private ?AuthProvider $auth = null;
    private string $baseUrl = Headers::DEFAULT_BASE_URL;
    private string $anthropicVersion = Headers::DEFAULT_API_VERSION;
    /** @var list<string> */
    private array $anthropicBeta = [];
    private ?RetryPolicy $retryPolicy = null;
    private ?ClientInterface $httpClient = null;
    private ?RequestFactoryInterface $requestFactory = null;
    private ?StreamFactoryInterface $streamFactory = null;
    private float $timeout = 600.0;
    private ?string $userAgentSuffix = null;
    private ?LoggerInterface $logger = null;
    private bool $logBodies = false;

    public function withApiKey(string $apiKey): self
    {
        $this->auth = new ApiKeyAuth($apiKey);

        return $this;
    }

    public function withAuth(AuthProvider $auth): self
    {
        $this->auth = $auth;

        return $this;
    }

    public function withBaseUrl(string $url): self
    {
        $this->baseUrl = $url;

        return $this;
    }

    public function withAnthropicVersion(string $version): self
    {
        $this->anthropicVersion = $version;

        return $this;
    }

    public function withAnthropicBeta(string ...$flags): self
    {
        $this->anthropicBeta = array_values($flags);

        return $this;
    }

    public function withRetryPolicy(RetryPolicy $policy): self
    {
        $this->retryPolicy = $policy;

        return $this;
    }

    public function withHttpClient(ClientInterface $client): self
    {
        $this->httpClient = $client;

        return $this;
    }

    public function withRequestFactory(RequestFactoryInterface $factory): self
    {
        $this->requestFactory = $factory;

        return $this;
    }

    public function withStreamFactory(StreamFactoryInterface $factory): self
    {
        $this->streamFactory = $factory;

        return $this;
    }

    public function withTimeout(float $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function withUserAgentSuffix(string $suffix): self
    {
        $this->userAgentSuffix = $suffix;

        return $this;
    }

    /**
     * Attach a PSR-3 logger. The SDK emits one info-level entry per
     * request and one per response (or error) sharing a correlation id.
     * Pass `$logBodies = true` for verbose debugging only — bodies may
     * contain user PII or model output.
     */
    public function withLogger(LoggerInterface $logger, bool $logBodies = false): self
    {
        $this->logger = $logger;
        $this->logBodies = $logBodies;

        return $this;
    }

    public function build(): Client
    {
        if ($this->auth === null) {
            $envKey = getenv('ANTHROPIC_API_KEY');
            if ($envKey === false || $envKey === '') {
                throw new \LogicException(
                    'No auth configured. Call withApiKey(), withAuth(), or set ANTHROPIC_API_KEY.',
                );
            }
            $this->auth = new ApiKeyAuth($envKey);
        }

        $options = new ClientOptions(
            auth: $this->auth,
            baseUrl: $this->baseUrl,
            anthropicVersion: $this->anthropicVersion,
            anthropicBeta: $this->anthropicBeta,
            retryPolicy: $this->retryPolicy ?? new RetryPolicy(),
            timeout: $this->timeout,
            userAgentSuffix: $this->userAgentSuffix,
            logger: $this->logger,
            logBodies: $this->logBodies,
        );

        $httpClient = $this->httpClient ?? Client::discoverHttpClient();

        return Client::fromComponents(
            $options,
            $httpClient,
            $this->requestFactory,
            $this->streamFactory,
        );
    }
}
