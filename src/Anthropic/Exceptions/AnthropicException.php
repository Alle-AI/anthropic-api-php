<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/**
 * Base class for every exception thrown by this SDK.
 *
 * Catch this to handle any error from the library:
 *
 * ```php
 * try {
 *     $client->messages()->create(...);
 * } catch (AnthropicException $e) {
 *     // any SDK error
 * }
 * ```
 */
class AnthropicException extends \RuntimeException
{
}
