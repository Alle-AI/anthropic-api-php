<?php

declare(strict_types=1);

namespace AlleAI\Anthropic;

use AlleAI\Anthropic\Auth\AuthProvider;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\RetryPolicy;

/**
 * Immutable configuration for a {@see Client}. Construct via {@see ClientBuilder}.
 */
final readonly class ClientOptions
{
    /**
     * @param  list<string>  $anthropicBeta  values for the anthropic-beta header
     */
    public function __construct(
        public AuthProvider $auth,
        public string $baseUrl,
        public string $anthropicVersion,
        public array $anthropicBeta,
        public RetryPolicy $retryPolicy,
        public float $timeout,
        public ?string $userAgentSuffix,
    ) {
    }

    public static function default(AuthProvider $auth): self
    {
        return new self(
            auth: $auth,
            baseUrl: Headers::DEFAULT_BASE_URL,
            anthropicVersion: Headers::DEFAULT_API_VERSION,
            anthropicBeta: [],
            retryPolicy: new RetryPolicy(),
            timeout: 600.0,
            userAgentSuffix: null,
        );
    }
}
