<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http;

/**
 * Header name and default-value constants used throughout the SDK.
 */
final class Headers
{
    public const ANTHROPIC_VERSION = 'anthropic-version';
    public const ANTHROPIC_BETA = 'anthropic-beta';
    public const X_API_KEY = 'x-api-key';
    public const CONTENT_TYPE = 'content-type';
    public const ACCEPT = 'accept';
    public const USER_AGENT = 'user-agent';
    public const X_REQUEST_ID = 'x-request-id';
    public const RETRY_AFTER = 'retry-after';
    public const IDEMPOTENCY_KEY = 'anthropic-idempotency-key';

    /**
     * Default API version. Bump when Anthropic ships a new stable version
     * and the SDK has been updated to handle response shape changes.
     */
    public const DEFAULT_API_VERSION = '2023-06-01';

    /**
     * Default base URL for the Anthropic API.
     */
    public const DEFAULT_BASE_URL = 'https://api.anthropic.com';

    private function __construct()
    {
    }
}
