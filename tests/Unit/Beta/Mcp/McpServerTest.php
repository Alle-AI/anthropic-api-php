<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Beta\Mcp;

use AlleAI\Anthropic\Beta\Mcp\McpServer;
use AlleAI\Anthropic\Beta\Mcp\McpToolApproval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(McpServer::class)]
#[CoversClass(McpToolApproval::class)]
final class McpServerTest extends TestCase
{
    public function testUrlServerSerialisesMinimalShape(): void
    {
        $server = McpServer::url(name: 'notion', url: 'https://x/');
        self::assertSame([
            'type' => 'url',
            'name' => 'notion',
            'url' => 'https://x/',
        ], $server->toArray());
    }

    public function testFullShape(): void
    {
        $server = McpServer::url(
            name: 'demo',
            url: 'https://x/',
            authorizationToken: 'secret',
            toolConfiguration: ['enabled' => true, 'allowed_tools' => ['search']],
            toolApproval: McpToolApproval::never(),
        );
        $arr = $server->toArray();
        self::assertSame('secret', $arr['authorization_token']);
        self::assertSame(['enabled' => true, 'allowed_tools' => ['search']], $arr['tool_configuration']);
        self::assertSame('never', $arr['tool_approval']);
    }

    public function testCustomApprovalSerialisesAsObject(): void
    {
        $approval = McpToolApproval::custom(
            mode: 'always',
            alwaysAllowed: ['search'],
            neverAllowed: ['write'],
        );
        self::assertSame(
            ['mode' => 'always', 'always_allowed' => ['search'], 'never_allowed' => ['write']],
            $approval->toWire(),
        );
    }

    public function testStringApprovalShortcuts(): void
    {
        self::assertSame('always', McpToolApproval::always()->toWire());
        self::assertSame('never', McpToolApproval::never()->toWire());
        self::assertSame('unless_disallowed', McpToolApproval::unlessDisallowed()->toWire());
    }
}
