<?php

declare(strict_types=1);

namespace Alle_AI\Anthropic;

use AlleAI\Anthropic\Auth\ApiKeyAuth;
use AlleAI\Anthropic\Client;
use AlleAI\Anthropic\ClientOptions;
use AlleAI\Anthropic\Exceptions\AnthropicException;
use AlleAI\Anthropic\Exceptions\DeprecationException;
use AlleAI\Anthropic\Http\Headers;
use AlleAI\Anthropic\Http\RetryPolicy;
use AlleAI\Anthropic\Util\Json;

/**
 * @deprecated since 2.0.0, will be removed in 3.0.0.
 *             Migrate to {@see \AlleAI\Anthropic\Client}.
 *
 * Legacy v1.x compatibility shim. Existing code written against
 * `Alle_AI\Anthropic\AnthropicAPI` keeps working but emits an
 * E_USER_DEPRECATED notice on construction and on every call.
 *
 * Set the environment variable `ALLE_AI_ANTHROPIC_FAIL_ON_DEPRECATED=1`
 * to convert these notices into a {@see DeprecationException} during
 * migration — useful for surfacing every legacy call site.
 *
 * See UPGRADING.md for the v1 → v2 migration guide.
 */
class AnthropicAPI
{
    private string $apiKey;
    private string $version;
    private ?Client $client = null;

    public function __construct(string $apiKey, string $version)
    {
        $this->apiKey = $apiKey;
        $this->version = $version;

        $this->emitDeprecation(
            'Alle_AI\\Anthropic\\AnthropicAPI is deprecated since 2.0.0 and will be removed in 3.0.0. '
            . 'Migrate to AlleAI\\Anthropic\\Client. See UPGRADING.md.',
        );
    }

    /**
     * Legacy entry point. Routes to the v2 client.
     *
     * - `$apiType = 'messages'` → `POST /v1/messages` (recommended).
     * - `$apiType = 'complete'` → the legacy `/v1/complete` text completions
     *   endpoint, returned as-is for old code that still depends on it.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<array-key, mixed>
     */
    public function generateText(array $data, string $apiType = 'complete'): array
    {
        $this->emitDeprecation(sprintf(
            'AnthropicAPI::generateText(api_type="%s") is deprecated. '
            . 'Use AlleAI\\Anthropic\\Client->messages()->create(...) instead.',
            $apiType,
        ));

        return match ($apiType) {
            'messages' => $this->callMessages($data),
            default => $this->callRawEndpoint($apiType, $data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @return array<array-key, mixed>
     */
    private function callMessages(array $data): array
    {
        $client = $this->newClient();

        /** @var int $maxTokens */
        $maxTokens = isset($data['max_tokens']) && is_numeric($data['max_tokens'])
            ? (int) $data['max_tokens']
            : (isset($data['max_tokens_to_sample']) && is_numeric($data['max_tokens_to_sample'])
                ? (int) $data['max_tokens_to_sample']
                : 1024);

        $model = isset($data['model']) && is_string($data['model']) ? $data['model'] : '';
        /** @var list<array<string, mixed>> $messages */
        $messages = isset($data['messages']) && is_array($data['messages']) ? $data['messages'] : [];

        $response = $client->messages()->create(
            model: $model,
            maxTokens: $maxTokens,
            messages: $messages,
            system: isset($data['system']) && (is_string($data['system']) || is_array($data['system']))
                ? $data['system']
                : null,
            temperature: isset($data['temperature']) && is_numeric($data['temperature'])
                ? (float) $data['temperature']
                : null,
            stopSequences: isset($data['stop_sequences']) && is_array($data['stop_sequences'])
                /** @phpstan-ignore-next-line — defensive cast */
                ? $data['stop_sequences']
                : null,
        );

        return $response->raw;
    }

    /**
     * Calls a raw Anthropic endpoint (e.g. the legacy `/v1/complete`) with the
     * provided JSON body. Reuses the v2 transport but bypasses typed parsing.
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<array-key, mixed>
     */
    private function callRawEndpoint(string $apiType, array $data): array
    {
        $url = rtrim(Headers::DEFAULT_BASE_URL, '/') . '/v1/' . ltrim($apiType, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                Headers::X_API_KEY . ': ' . $this->apiKey,
                Headers::ANTHROPIC_VERSION . ': ' . $this->version,
                Headers::CONTENT_TYPE . ': application/json',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => Json::encode($data),
            CURLOPT_TIMEOUT => 600,
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($body)) {
            throw new AnthropicException('cURL error contacting Anthropic API: ' . $error);
        }

        return Json::decode($body);
    }

    private function newClient(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $options = new ClientOptions(
            auth: new ApiKeyAuth($this->apiKey),
            baseUrl: Headers::DEFAULT_BASE_URL,
            anthropicVersion: $this->version,
            anthropicBeta: [],
            retryPolicy: new RetryPolicy(),
            timeout: 600.0,
            userAgentSuffix: 'legacy-shim',
        );

        return $this->client = Client::fromComponents($options, Client::discoverHttpClient());
    }

    private function emitDeprecation(string $message): void
    {
        $failLoud = getenv('ALLE_AI_ANTHROPIC_FAIL_ON_DEPRECATED');
        if ($failLoud === '1' || $failLoud === 'true') {
            throw new DeprecationException($message);
        }

        @trigger_error($message, E_USER_DEPRECATED);
    }
}
