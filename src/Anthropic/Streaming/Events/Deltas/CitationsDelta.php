<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Streaming\Events\Deltas;

/**
 * Citation incrementally attached to a content block.
 */
final readonly class CitationsDelta implements Delta
{
    /**
     * @param  array<string, mixed>  $citation
     */
    public function __construct(public array $citation) {}

    public function type(): string
    {
        return 'citations_delta';
    }
}
