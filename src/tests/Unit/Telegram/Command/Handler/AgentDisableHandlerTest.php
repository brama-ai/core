<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\Command\Handler\AgentDisableHandler;
use App\Channel\DTO\NormalizedChat;
use App\Channel\DTO\NormalizedEvent;
use App\Channel\DTO\NormalizedMessage;
use App\Channel\DTO\NormalizedSender;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class AgentDisableHandlerTest extends Unit
{
    private AgentRegistryInterface&MockObject $agentRegistry;
    private AgentDisableHandler $handler;

    protected function setUp(): void
    {
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->handler = new AgentDisableHandler($this->agentRegistry);
    }

    public function testHandleRepliesAgentNotFoundWhenMissing(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('missing-agent')
            ->willReturn(null);

        $payload = $this->handler->handle($event, 'missing-agent');

        $this->assertStringContainsString('не знайдений', $payload->text);
    }

    public function testHandleRepliesAlreadyDisabledWhenAgentIsDisabled(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('my-agent')
            ->willReturn(['name' => 'my-agent', 'enabled' => false]);

        $payload = $this->handler->handle($event, 'my-agent');

        $this->assertStringContainsString('вже вимкнений', $payload->text);
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

        $payload = $this->handler->handle($event, 'my-agent');

        $this->assertStringContainsString('вимкнений', $payload->text);
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

        $payload = $this->handler->handle($event, 'my-agent');

        $this->assertStringContainsString('Не вдалося вимкнути', $payload->text);
    }

    public function testHandleIncludesThreadIdInTargetAddressWhenPresent(): void
    {
        $event = $this->buildEvent('chat-1', '33');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(null);

        $payload = $this->handler->handle($event, 'missing-agent');

        $this->assertStringContainsString('chat-1:33', $payload->target->address);
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
