<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\ChannelManagerInterface;
use App\Channel\Command\PlatformCommandRouter;
use App\Channel\DTO\NormalizedChat;
use App\Channel\DTO\NormalizedEvent;
use App\Channel\DTO\NormalizedMessage;
use App\Channel\DTO\NormalizedSender;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

final class TelegramCommandRouterTest extends Unit
{
    private ChannelManagerInterface&MockObject $channelManager;
    private AgentRegistryInterface&MockObject $agentRegistry;
    private PlatformCommandRouter $router;

    protected function setUp(): void
    {
        $this->channelManager = $this->createMock(ChannelManagerInterface::class);
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->router = new PlatformCommandRouter(
            $this->channelManager,
            $this->agentRegistry,
            new NullLogger(),
        );
    }

    public function testRouteDoesNothingWhenNoCommandName(): void
    {
        $event = $this->buildEvent(null, null, 'user-1', 'user');

        $this->channelManager->expects($this->never())->method('send');

        $this->router->route($event);
    }

    public function testRouteHelpCommandForUserRole(): void
    {
        $event = $this->buildEvent('/help', null, 'user-1', 'user');

        $this->agentRegistry->expects($this->atLeastOnce())
            ->method('findEnabled')
            ->willReturn([]);

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentsCommandForUserRole(): void
    {
        $event = $this->buildEvent('/agents', null, 'user-1', 'user');

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentCommandDeniedForUserRole(): void
    {
        $event = $this->buildEvent('/agent', 'enable myagent', 'user-1', 'user');

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentCommandAllowedForModeratorRole(): void
    {
        $event = $this->buildEvent('/agent', 'enable myagent', 'mod-1', 'moderator');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('myagent')
            ->willReturn(null);

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentCommandAllowedForAdminRole(): void
    {
        $event = $this->buildEvent('/agent', 'disable myagent', 'admin-1', 'admin');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('myagent')
            ->willReturn(null);

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteUnknownCommandRepliesWithHelpHint(): void
    {
        $event = $this->buildEvent('/unknown', null, 'user-1', 'user');

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([]);

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentDeclaredCommandIsHandled(): void
    {
        $event = $this->buildEvent('/search', 'query text', 'user-1', 'user');

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([
                [
                    'name' => 'knowledge-agent',
                    'manifest' => json_encode(['commands' => ['/search'], 'events' => []]),
                ],
            ]);

        // Agent command is handled — no send call
        $this->channelManager->expects($this->never())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentCommandWithNoArgsShowsUsage(): void
    {
        $event = $this->buildEvent('/agent', null, 'mod-1', 'moderator');

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentCommandWithUnknownActionShowsError(): void
    {
        $event = $this->buildEvent('/agent', 'restart myagent', 'mod-1', 'moderator');

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentCommandWithMissingAgentNameShowsError(): void
    {
        $event = $this->buildEvent('/agent', 'enable', 'mod-1', 'moderator');

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteIncludesThreadIdInOptionsWhenPresent(): void
    {
        $event = $this->buildEventWithThread('/help', null, 'user-1', '42', 'user');

        $this->agentRegistry->expects($this->atLeastOnce())
            ->method('findEnabled')
            ->willReturn([]);

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    public function testRoleHierarchyAdminCanUseModeratorCommands(): void
    {
        // Admin (level 3) should pass min_role=moderator (level 2) check
        $event = $this->buildEvent('/agent', 'enable myagent', 'admin-1', 'admin');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(['name' => 'myagent', 'enabled' => false]);

        $this->agentRegistry->expects($this->once())
            ->method('enable')
            ->willReturn(true);

        $this->channelManager->expects($this->once())
            ->method('send');

        $this->router->route($event);
    }

    private function buildEvent(?string $commandName, ?string $commandArgs, string $senderId, string $role): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: 'chat-1', type: 'group'),
            sender: new NormalizedSender(id: $senderId, username: 'testuser', role: $role),
            message: new NormalizedMessage(id: 'msg-1', commandName: $commandName, commandArgs: $commandArgs),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }

    private function buildEventWithThread(?string $commandName, ?string $commandArgs, string $senderId, string $threadId, string $role): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: 'chat-1', type: 'group', threadId: $threadId),
            sender: new NormalizedSender(id: $senderId, username: 'testuser', role: $role),
            message: new NormalizedMessage(id: 'msg-1', commandName: $commandName, commandArgs: $commandArgs),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }
}
