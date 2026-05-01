<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/** HTTP 500 — server-side error. Generally retryable. */
final class InternalServerException extends ApiException
{
}
