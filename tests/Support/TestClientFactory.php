<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Support;

use AlleAI\Anthropic\Auth\ApiKeyAuth;
use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\ClientOptions;
use AlleAI\Anthropic\Http\ConcurrentSender;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\RetryPolicy;

/**
 * Builds a {@see Client} backed by a {@see FakePsr18Client} so contract
 * tests can exercise the resource layer without network IO.
 */
final class TestClientFactory
{
    /**
     * @param  list<string>  $beta
     *
     * @return array{Client, FakePsr18Client}
     */
    public static function make(
        string $apiKey = 'test-key',
        ?RetryPolicy $retry = null,
        array $beta = [],
        ?ConcurrentSender $concurrentSender = null,
    ): array {
        $http = new FakePsr18Client();
        $options = new ClientOptions(
            auth: new ApiKeyAuth($apiKey),
            baseUrl: Headers::DEFAULT_BASE_URL,
            anthropicVersion: Headers::DEFAULT_API_VERSION,
            anthropicBeta: $beta,
            retryPolicy: $retry ?? RetryPolicy::disabled(),
            timeout: 5.0,
            userAgentSuffix: null,
        );

        $client = Client::fromComponents(
            $options,
            $http,
            null,
            null,
            $concurrentSender,
        );

        return [$client, $http];
    }
}
