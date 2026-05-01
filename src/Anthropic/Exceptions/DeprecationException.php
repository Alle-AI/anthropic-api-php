<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/**
 * Thrown by the v1 deprecation shim when the user has opted into
 * fail-loud mode by setting ALLE_AI_ANTHROPIC_FAIL_ON_DEPRECATED=1.
 *
 * Useful during migration to surface every legacy call site.
 */
final class DeprecationException extends AnthropicException
{
}
