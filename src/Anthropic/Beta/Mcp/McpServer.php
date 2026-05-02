<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Beta\Mcp;

/**
 * Definition of one MCP server passed via the `mcp_servers` field on a
 * Messages request. Anthropic's hosted connector connects to this server
 * on Claude's behalf and exposes its tools/resources/prompts to the model.
 *
 * ```php
 * $client->messages()->create(
 *     model: Model::CLAUDE_SONNET_4_7,
 *     maxTokens: 4096,
 *     messages: [['role' => 'user', 'content' => 'Search Notion for Q1 OKRs.']],
 *     mcpServers: [
 *         McpServer::url(
 *             name: 'notion',
 *             url: 'https://mcp.notion.com/mcp',
 *             authorizationToken: $token,
 *             toolApproval: McpToolApproval::never(),
 *         )->toArray(),
 *     ],
 *     extraHeaders: ['anthropic-beta' => 'mcp-client-2025-04-04'],
 * );
 * ```
 *
 * Caller is responsible for adding the {@see \AlleAI\Anthropic\Beta\BetaHeaders::MCP_CLIENT}
 * beta header — the SDK keeps MCP under the explicit beta namespace so the
 * graduation path is honest.
 */
final readonly class McpServer
{
    /**
     * @param  array<string, mixed>|null  $toolConfiguration
     */
    private function __construct(
        public string $type,
        public string $name,
        public string $url,
        public ?string $authorizationToken,
        public ?array $toolConfiguration,
        public ?McpToolApproval $toolApproval,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $toolConfiguration
     */
    public static function url(
        string $name,
        string $url,
        ?string $authorizationToken = null,
        ?array $toolConfiguration = null,
        ?McpToolApproval $toolApproval = null,
    ): self {
        return new self(
            type: 'url',
            name: $name,
            url: $url,
            authorizationToken: $authorizationToken,
            toolConfiguration: $toolConfiguration,
            toolApproval: $toolApproval,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'type' => $this->type,
            'name' => $this->name,
            'url' => $this->url,
        ];

        if ($this->authorizationToken !== null) {
            $out['authorization_token'] = $this->authorizationToken;
        }
        if ($this->toolConfiguration !== null) {
            $out['tool_configuration'] = $this->toolConfiguration;
        }
        if ($this->toolApproval !== null) {
            $out['tool_approval'] = $this->toolApproval->toWire();
        }

        return $out;
    }
}
