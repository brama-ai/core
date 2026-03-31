<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Telegram\Command\Handler\AgentDisableHandler;
use App\Telegram\DTO\NormalizedChat;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\DTO\NormalizedMessage;
use App\Telegram\DTO\NormalizedSender;
use App\Telegram\Service\TelegramSenderInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class AgentDisableHandlerTest extends Unit
{
    private AgentRegistryInterface&MockObject $agentRegistry;
    private TelegramSenderInterface&MockObject $sender;
    private AgentDisableHandler $handler;

    protected function setUp(): void
    {
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->sender = $this->createMock(TelegramSenderInterface::class);
        $this->handler = new AgentDisableHandler($this->agentRegistry);
    }

    public function testHandleRepliesAgentNotFoundWhenMissing(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('missing-agent')
            ->willReturn(null);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('не знайдений'), []);

        $this->handler->handle($event, $this->sender, 'missing-agent');
    }

    public function testHandleRepliesAlreadyDisabledWhenAgentIsDisabled(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('my-agent')
            ->willReturn(['name' => 'my-agent', 'enabled' => false]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('вже вимкнений'), []);

        $this->handler->handle($event, $this->sender, 'my-agent');
    }

    public function testHandleDisablesAgentAndConfirms(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('my-agent')
            ->willReturn(['name' => 'my-agent', 'enabled' => true]);

        $this->agentRegistry->expects($this->once())
            ->method('disable')
            ->with('my-agent')
            ->willReturn(true);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('вимкнений'), []);

        $this->handler->handle($event, $this->sender, 'my-agent');
    }

    public function testHandleRepliesFailureWhenDisableFails(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(['name' => 'my-agent', 'enabled' => true]);

        $this->agentRegistry->expects($this->once())
            ->method('disable')
            ->willReturn(false);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('Не вдалося вимкнути'), []);

        $this->handler->handle($event, $this->sender, 'my-agent');
    }

    public function testHandleIncludesThreadIdInOptionsWhenPresent(): void
    {
        $event = $this->buildEvent('chat-1', '33');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(null);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->isString(), ['thread_id' => '33']);

        $this->handler->handle($event, $this->sender, 'missing-agent');
    }

    private function buildEvent(string $chatId, ?string $threadId): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: $chatId, type: 'group', threadId: $threadId),
            sender: new NormalizedSender(id: 'user-1'),
            message: new NormalizedMessage(id: 'msg-1', commandName: '/agent', commandArgs: 'disable my-agent'),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }
}
