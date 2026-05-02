<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

use AlleAI\Anthropic\Util\Json;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Maps a non-2xx PSR-7 response into the appropriate ApiException subclass.
 */
final class ExceptionFactory
{
    public static function fromResponse(
        ResponseInterface $response,
        ?RequestInterface $request = null,
    ): ApiException {
        $status = $response->getStatusCode();
        $rawBody = (string) $response->getBody();

        $errorType = null;
        $message = sprintf('Anthropic API error: HTTP %d', $status);

        if ($rawBody !== '') {
            try {
                $decoded = Json::decode($rawBody);
                if (isset($decoded['error']) && is_array($decoded['error'])) {
                    $errorType = isset($decoded['error']['type']) && is_string($decoded['error']['type'])
                        ? $decoded['error']['type']
                        : null;
                    $apiMessage = isset($decoded['error']['message']) && is_string($decoded['error']['message'])
                        ? $decoded['error']['message']
                        : null;
                    if ($apiMessage !== null) {
                        $message = sprintf('%s [%s]', $apiMessage, $errorType ?? (string) $status);
                    }
                }
            } catch (AnthropicException) {
                // body wasn't valid JSON — fall through with the generic message
            }
        }

        /** @var array<string, list<string>> $headers */
        $headers = $response->getHeaders();
        $requestIdHeader = $response->getHeaderLine('x-request-id');
        $requestId = $requestIdHeader !== '' ? $requestIdHeader : null;

        $class = match (true) {
            $status === 400 => BadRequestException::class,
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionDeniedException::class,
            $status === 404 => NotFoundException::class,
            $status === 413 => RequestTooLargeException::class,
            $status === 422 => UnprocessableEntityException::class,
            $status === 429 => RateLimitException::class,
            $status === 529 => OverloadedException::class,
            $status >= 500 => InternalServerException::class,
            default => ApiException::class,
        };

        return new $class(
            message: $message,
            status: $status,
            errorType: $errorType,
            requestId: $requestId,
            headers: $headers,
            rawBody: $rawBody,
            request: $request,
            response: $response,
        );
    }
}
