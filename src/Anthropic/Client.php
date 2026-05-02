<?php

declare(strict_types=1);

namespace AlleAI\Anthropic;

use AlleAI\Anthropic\Auth\ApiKeyAuth;
use AlleAI\Anthropic\Http\CurlStreamTransport;
use AlleAI\Anthropic\Http\Middleware\AuthMiddleware;
use AlleAI\Anthropic\Http\Middleware\IdempotencyMiddleware;
use AlleAI\Anthropic\Http\Middleware\RetryMiddleware;
use AlleAI\Anthropic\Http\Middleware\UserAgentMiddleware;
use AlleAI\Anthropic\Http\Psr18Transport;
use AlleAI\Anthropic\Http\Transport;
use AlleAI\Anthropic\Resources\Messages;
use AlleAI\Anthropic\Util\SystemSleeper;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Top-level entry point for the Anthropic API.
 *
 * Construct with {@see Client::fromApiKey()} for the common case, or use
 * {@see Client::builder()} for full control over HTTP client, retries,
 * auth provider, and middleware.
 *
 * ```php
 * $client = Client::fromApiKey(getenv('ANTHROPIC_API_KEY'));
 * $response = $client->messages()->create(
 *     model: Model::CLAUDE_SONNET_4_7,
 *     maxTokens: 1024,
 *     messages: [['role' => 'user', 'content' => 'Hello, Claude!']],
 * );
 * echo $response->text();
 * ```
 */
final class Client
{
    private ?Messages $messagesResource = null;

    public function __construct(
        private readonly ClientOptions $options,
        private readonly Transport $transport,
        private readonly CurlStreamTransport $streamTransport,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Most common construction path: create a client from an API key string.
     *
     * Auto-discovers a PSR-18 HTTP client and PSR-17 factories via
     * php-http/discovery — install guzzlehttp/guzzle and nyholm/psr7 (or
     * any other compatible pair) for this to work out of the box.
     */
    public static function fromApiKey(string $apiKey): self
    {
        return self::builder()->withApiKey($apiKey)->build();
    }

    /**
     * Build the same client by reading ANTHROPIC_API_KEY from the environment.
     */
    public static function fromEnvironment(string $varName = 'ANTHROPIC_API_KEY'): self
    {
        return self::builder()->withAuth(ApiKeyAuth::fromEnvironment($varName))->build();
    }

    public static function builder(): ClientBuilder
    {
        return new ClientBuilder();
    }

    public function options(): ClientOptions
    {
        return $this->options;
    }

    public function messages(): Messages
    {
        return $this->messagesResource ??= new Messages(
            $this->transport,
            $this->streamTransport,
            $this->requestFactory,
            $this->streamFactory,
            $this->options,
        );
    }

    /**
     * @internal  Used by the legacy shim. Public for testing.
     */
    public static function fromComponents(
        ClientOptions $options,
        ClientInterface $httpClient,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();

        $middleware = [
            new AuthMiddleware($options->auth),
            new UserAgentMiddleware($options->userAgentSuffix),
            new IdempotencyMiddleware(),
            new RetryMiddleware($options->retryPolicy, new SystemSleeper()),
        ];

        $transport = new Psr18Transport($httpClient, $middleware);

        $streamTransport = new CurlStreamTransport(
            auth: $options->auth,
            userAgent: sprintf(
                '%s/%s php/%s',
                UserAgentMiddleware::SDK_NAME,
                UserAgentMiddleware::SDK_VERSION,
                PHP_VERSION,
            ),
            totalTimeout: $options->timeout,
        );

        return new self($options, $transport, $streamTransport, $requestFactory, $streamFactory);
    }

    /**
     * Auto-discover a PSR-18 client. Used internally by {@see ClientBuilder}.
     *
     * @internal
     */
    public static function discoverHttpClient(): ClientInterface
    {
        return Psr18ClientDiscovery::find();
    }
}
