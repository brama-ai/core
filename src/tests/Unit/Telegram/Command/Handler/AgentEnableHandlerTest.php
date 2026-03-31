<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Telegram\Command\Handler\AgentEnableHandler;
use App\Telegram\DTO\NormalizedChat;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\DTO\NormalizedMessage;
use App\Telegram\DTO\NormalizedSender;
use App\Telegram\Service\TelegramSenderInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class AgentEnableHandlerTest extends Unit
{
    private AgentRegistryInterface&MockObject $agentRegistry;
    private TelegramSenderInterface&MockObject $sender;
    private AgentEnableHandler $handler;

    protected function setUp(): void
    {
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->sender = $this->createMock(TelegramSenderInterface::class);
        $this->handler = new AgentEnableHandler($this->agentRegistry);
    }

    public function testHandleRepliesAgentNotFoundWhenMissing(): void
    {
        $event = $this->buildEvent('user-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('missing-agent')
            ->willReturn(null);

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->stringContains('не знайдений'),
                [],
            );

        $this->handler->handle($event, $this->sender, 'missing-agent', 'moderator');
    }

    public function testHandleRepliesAlreadyEnabledWhenAgentIsEnabled(): void
    {
        $event = $this->buildEvent('user-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('my-agent')
            ->willReturn(['name' => 'my-agent', 'enabled' => true]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('вже увімкнений'), []);

        $this->handler->handle($event, $this->sender, 'my-agent', 'moderator');
    }

    public function testHandleEnablesAgentAndConfirms(): void
    {
        $event = $this->buildEvent('user-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('my-agent')
            ->willReturn(['name' => 'my-agent', 'enabled' => false]);

        $this->agentRegistry->expects($this->once())
            ->method('enable')
            ->with('my-agent', $this->isString())
            ->willReturn(true);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('увімкнений'), []);

        $this->handler->handle($event, $this->sender, 'my-agent', 'moderator');
    }

    public function testHandleRepliesFailureWhenEnableFails(): void
    {
        $event = $this->buildEvent('user-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(['name' => 'my-agent', 'enabled' => false]);

        $this->agentRegistry->expects($this->once())
            ->method('enable')
            ->willReturn(false);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('Не вдалося увімкнути'), []);

        $this->handler->handle($event, $this->sender, 'my-agent', 'moderator');
    }

    public function testHandleUsesUsernameAsEnabledByWhenAvailable(): void
    {
        $event = $this->buildEventWithUsername('user-1', 'moderator_user', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(['name' => 'my-agent', 'enabled' => false]);

        $this->agentRegistry->expects($this->once())
            ->method('enable')
            ->with('my-agent', 'moderator_user')
            ->willReturn(true);

        $this->sender->expects($this->once())
            ->method('send');

        $this->handler->handle($event, $this->sender, 'my-agent', 'moderator');
    }

    public function testHandleUsesUserIdAsEnabledByWhenNoUsername(): void
    {
        $event = $this->buildEvent('user-42', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(['name' => 'my-agent', 'enabled' => false]);

        $this->agentRegistry->expects($this->once())
            ->method('enable')
            ->with('my-agent', 'user-42')
            ->willReturn(true);

        $this->sender->expects($this->once())
            ->method('send');

        $this->handler->handle($event, $this->sender, 'my-agent', 'moderator');
    }

    public function testHandleIncludesThreadIdInOptionsWhenPresent(): void
    {
        $event = $this->buildEvent('user-1', '77');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(null);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->isString(), ['thread_id' => '77']);

        $this->handler->handle($event, $this->sender, 'missing-agent', 'moderator');
    }

    private function buildEvent(string $senderId, ?string $threadId): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: 'chat-1', type: 'group', threadId: $threadId),
            sender: new NormalizedSender(id: $senderId),
            message: new NormalizedMessage(id: 'msg-1', commandName: '/agent', commandArgs: 'enable my-agent'),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }

    private function buildEventWithUsername(string $senderId, string $username, ?string $threadId): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: 'chat-1', type: 'group', threadId: $threadId),
            sender: new NormalizedSender(id: $senderId, username: $username),
            message: new NormalizedMessage(id: 'msg-1', commandName: '/agent', commandArgs: 'enable my-agent'),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }
}
