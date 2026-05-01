<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/** HTTP 529 — Anthropic API is overloaded. Retry with backoff. */
final class OverloadedException extends ApiException
{
}
