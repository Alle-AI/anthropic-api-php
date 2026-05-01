<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/** Malformed SSE frame, premature stream close, or unexpected event payload. */
final class StreamException extends AnthropicException
{
}
