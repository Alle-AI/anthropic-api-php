<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Exceptions;

/** Connection-level transport failure (refused, reset, DNS, TLS). */
final class ConnectionException extends RequestException
{
}
