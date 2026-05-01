<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Messages;

enum Role: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';
}
