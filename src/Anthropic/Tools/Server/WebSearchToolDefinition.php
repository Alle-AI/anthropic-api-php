<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tools\Server;

use AlleAI\Anthropic\Messages\Content\CacheControl;

/**
 * Server-side web-search tool. Anthropic runs the search and returns
 * `web_search_tool_result` content blocks.
 *
 * ```php
 * $tool = WebSearchToolDefinition::create(
 *     maxUses: 3,
 *     allowedDomains: ['example.com', 'wikipedia.org'],
 * );
 * ```
 */
final readonly class WebSearchToolDefinition implements ServerToolDefinition
{
    public const BETA_HEADER = 'web-search-2025-03-05';
    public const TYPE = 'web_search_20250305';
    public const NAME = 'web_search';

    /**
     * @param  list<string>|null  $allowedDomains
     * @param  list<string>|null  $blockedDomains
     * @param  array<string, mixed>|null  $userLocation
     */
    private function __construct(
        public ?int $maxUses = null,
        public ?array $allowedDomains = null,
        public ?array $blockedDomains = null,
        public ?array $userLocation = null,
        public ?CacheControl $cacheControl = null,
    ) {
        if ($allowedDomains !== null && $blockedDomains !== null) {
            throw new \InvalidArgumentException(
                'WebSearchToolDefinition: pass allowedDomains OR blockedDomains, not both.',
            );
        }
    }

    /**
     * @param  list<string>|null  $allowedDomains
     * @param  list<string>|null  $blockedDomains
     * @param  array<string, mixed>|null  $userLocation
     */
    public static function create(
        ?int $maxUses = null,
        ?array $allowedDomains = null,
        ?array $blockedDomains = null,
        ?array $userLocation = null,
        ?CacheControl $cacheControl = null,
    ): self {
        return new self($maxUses, $allowedDomains, $blockedDomains, $userLocation, $cacheControl);
    }

    public function toArray(): array
    {
        $out = ['type' => self::TYPE, 'name' => self::NAME];

        if ($this->maxUses !== null) {
            $out['max_uses'] = $this->maxUses;
        }
        if ($this->allowedDomains !== null) {
            $out['allowed_domains'] = $this->allowedDomains;
        }
        if ($this->blockedDomains !== null) {
            $out['blocked_domains'] = $this->blockedDomains;
        }
        if ($this->userLocation !== null) {
            $out['user_location'] = $this->userLocation;
        }
        if ($this->cacheControl !== null) {
            $out['cache_control'] = $this->cacheControl->toArray();
        }

        return $out;
    }

    public function betaHeader(): ?string
    {
        return self::BETA_HEADER;
    }
}
