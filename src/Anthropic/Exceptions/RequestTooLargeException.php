<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/** HTTP 413 — payload exceeds size limit. */
final class RequestTooLargeException extends ApiException
{
}
