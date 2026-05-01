<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/** HTTP 403 — API key lacks permission for the requested resource. */
final class PermissionDeniedException extends ApiException
{
}
