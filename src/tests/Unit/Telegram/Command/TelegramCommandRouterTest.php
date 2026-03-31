<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command;

use App\AgentRegistry\AgentRegistryInterface;
use App\Telegram\Command\TelegramCommandRouter;
use App\Telegram\DTO\NormalizedChat;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\DTO\NormalizedMessage;
use App\Telegram\DTO\NormalizedSender;
use App\Telegram\Service\TelegramRoleResolverInterface;
use App\Telegram\Service\TelegramSenderInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

final class TelegramCommandRouterTest extends Unit
{
    private TelegramRoleResolverInterface&MockObject $roleResolver;
    private TelegramSenderInterface&MockObject $sender;
    private AgentRegistryInterface&MockObject $agentRegistry;
    private TelegramCommandRouter $router;

    protected function setUp(): void
    {
        $this->roleResolver = $this->createMock(TelegramRoleResolverInterface::class);
        $this->sender = $this->createMock(TelegramSenderInterface::class);
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->router = new TelegramCommandRouter(
            $this->roleResolver,
            $this->sender,
            $this->agentRegistry,
            new NullLogger(),
        );
    }

    public function testRouteDoesNothingWhenNoCommandName(): void
    {
        $event = $this->buildEvent(null, null, 'user-1');

        $this->roleResolver->expects($this->never())->method('resolve');
        $this->sender->expects($this->never())->method('send');

        $this->router->route($event);
    }

    public function testRouteHelpCommandForUserRole(): void
    {
        $event = $this->buildEvent('/help', null, 'user-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->with('bot-1', 'chat-1', 'user-1')
            ->willReturn('user');

        $this->agentRegistry->expects($this->atLeastOnce())
            ->method('findEnabled')
            ->willReturn([]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('/help'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteAgentsCommandForUserRole(): void
    {
        $event = $this->buildEvent('/agents', null, 'user-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('user');

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('Агентів не зареєстровано'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteAgentCommandDeniedForUserRole(): void
    {
        $event = $this->buildEvent('/agent', 'enable myagent', 'user-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('user');

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('немає дозволу'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteAgentCommandAllowedForModeratorRole(): void
    {
        $event = $this->buildEvent('/agent', 'enable myagent', 'mod-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('moderator');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('myagent')
            ->willReturn(null);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('не знайдений'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteAgentCommandAllowedForAdminRole(): void
    {
        $event = $this->buildEvent('/agent', 'disable myagent', 'admin-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('admin');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('myagent')
            ->willReturn(null);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('не знайдений'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteUnknownCommandRepliesWithHelpHint(): void
    {
        $event = $this->buildEvent('/unknown', null, 'user-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('user');

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('/help'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteAgentDeclaredCommandIsHandled(): void
    {
        $event = $this->buildEvent('/search', 'query text', 'user-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('user');

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([
                [
                    'name' => 'knowledge-agent',
                    'manifest' => json_encode(['commands' => ['/search'], 'events' => []]),
                ],
            ]);

        // Agent command is handled — no "unknown command" reply
        $this->sender->expects($this->never())
            ->method('send');

        $this->router->route($event);
    }

    public function testRouteAgentCommandWithNoArgsShowsUsage(): void
    {
        $event = $this->buildEvent('/agent', null, 'mod-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('moderator');

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('Використання'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteAgentCommandWithUnknownActionShowsError(): void
    {
        $event = $this->buildEvent('/agent', 'restart myagent', 'mod-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('moderator');

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('Невідома дія'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteAgentCommandWithMissingAgentNameShowsError(): void
    {
        $event = $this->buildEvent('/agent', 'enable', 'mod-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('moderator');

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('Вкажіть назву агента'), $this->isArray());

        $this->router->route($event);
    }

    public function testRouteIncludesThreadIdInOptionsWhenPresent(): void
    {
        $event = $this->buildEventWithThread('/help', null, 'user-1', '42');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('user');

        $this->agentRegistry->expects($this->atLeastOnce())
            ->method('findEnabled')
            ->willReturn([]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->isString(),
                $this->callback(static fn (array $opts): bool => isset($opts['thread_id']) && '42' === $opts['thread_id']),
            );

        $this->router->route($event);
    }

    public function testRoleHierarchyAdminCanUseModeratorCommands(): void
    {
        // Admin (level 3) should pass min_role=moderator (level 2) check
        $event = $this->buildEvent('/agent', 'enable myagent', 'admin-1');

        $this->roleResolver->expects($this->once())
            ->method('resolve')
            ->willReturn('admin');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(['name' => 'myagent', 'enabled' => false]);

        $this->agentRegistry->expects($this->once())
            ->method('enable')
            ->willReturn(true);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('увімкнений'), $this->isArray());

        $this->router->route($event);
    }

    private function buildEvent(?string $commandName, ?string $commandArgs, string $senderId): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: 'chat-1', type: 'group'),
            sender: new NormalizedSender(id: $senderId, username: 'testuser'),
            message: new NormalizedMessage(id: 'msg-1', commandName: $commandName, commandArgs: $commandArgs),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }

    private function buildEventWithThread(?string $commandName, ?string $commandArgs, string $senderId, string $threadId): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: 'chat-1', type: 'group', threadId: $threadId),
            sender: new NormalizedSender(id: $senderId, username: 'testuser'),
            message: new NormalizedMessage(id: 'msg-1', commandName: $commandName, commandArgs: $commandArgs),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }
}
