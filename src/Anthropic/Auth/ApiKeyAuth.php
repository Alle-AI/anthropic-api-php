<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Auth;

use Psr\Http\Message\RequestInterface;

/**
 * Default auth provider — sends `x-api-key: ...`.
 *
 * Construct directly with the key, or pull from `ANTHROPIC_API_KEY` via
 * {@see ApiKeyAuth::fromEnvironment()}.
 */
final readonly class ApiKeyAuth implements AuthProvider
{
    public function __construct(private string $apiKey)
    {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('Anthropic API key cannot be empty.');
        }
    }

    public static function fromEnvironment(string $varName = 'ANTHROPIC_API_KEY'): self
    {
        $value = getenv($varName);
        if ($value === false || $value === '') {
            throw new \RuntimeException(sprintf(
                'Environment variable %s is not set. Set it to your Anthropic API key.',
                $varName,
            ));
        }

        return new self($value);
    }

    public function authenticate(RequestInterface $request): array
    {
        return ['x-api-key' => $this->apiKey];
    }

    public function baseUrl(?string $configured): ?string
    {
        return $configured;
    }
}
