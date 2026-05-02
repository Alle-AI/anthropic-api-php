<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Http\Middleware;

use AlleAI\Anthropic\Http\Headers;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class UserAgentMiddleware implements Middleware
{
    public const SDK_NAME = 'alle-ai-anthropic-api-php';
    public const SDK_VERSION = '2.0.0-dev';

    public function __construct(private ?string $suffix = null)
    {
    }

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        if ($request->hasHeader(Headers::USER_AGENT)) {
            return $next($request);
        }

        $base = sprintf(
            '%s/%s php/%s curl/%s',
            self::SDK_NAME,
            self::SDK_VERSION,
            PHP_VERSION,
            $this->curlVersion(),
        );

        $value = $this->suffix !== null && $this->suffix !== ''
            ? $base . ' ' . $this->suffix
            : $base;

        return $next($request->withHeader(Headers::USER_AGENT, $value));
    }

    private function curlVersion(): string
    {
        if (!function_exists('curl_version')) {
            return 'unknown';
        }

        $info = curl_version();

        return is_array($info) && isset($info['version']) && is_string($info['version'])
            ? $info['version']
            : 'unknown';
    }
}
