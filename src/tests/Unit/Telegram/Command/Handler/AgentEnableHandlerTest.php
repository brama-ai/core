<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\Command\Handler\AgentEnableHandler;
use App\Channel\DTO\NormalizedChat;
use App\Channel\DTO\NormalizedEvent;
use App\Channel\DTO\NormalizedMessage;
use App\Channel\DTO\NormalizedSender;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class AgentEnableHandlerTest extends Unit
{
    private AgentRegistryInterface&MockObject $agentRegistry;
    private AgentEnableHandler $handler;

    protected function setUp(): void
    {
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->handler = new AgentEnableHandler($this->agentRegistry);
    }

    public function testHandleRepliesAgentNotFoundWhenMissing(): void
    {
        $event = $this->buildEvent('user-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('missing-agent')
            ->willReturn(null);

        $payload = $this->handler->handle($event, 'missing-agent', 'moderator');

        $this->assertStringContainsString('не знайдений', $payload->text);
    }

    public function testHandleRepliesAlreadyEnabledWhenAgentIsEnabled(): void
    {
        $event = $this->buildEvent('user-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->with('my-agent')
            ->willReturn(['name' => 'my-agent', 'enabled' => true]);

        $payload = $this->handler->handle($event, 'my-agent', 'moderator');

        $this->assertStringContainsString('вже увімкнений', $payload->text);
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

        $payload = $this->handler->handle($event, 'my-agent', 'moderator');

        $this->assertStringContainsString('увімкнений', $payload->text);
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

        $payload = $this->handler->handle($event, 'my-agent', 'moderator');

        $this->assertStringContainsString('Не вдалося увімкнути', $payload->text);
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

        $this->handler->handle($event, 'my-agent', 'moderator');
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

        $this->handler->handle($event, 'my-agent', 'moderator');
    }

    public function testHandleIncludesThreadIdInTargetAddressWhenPresent(): void
    {
        $event = $this->buildEvent('user-1', '77');

        $this->agentRegistry->expects($this->once())
            ->method('findByName')
            ->willReturn(null);

        $payload = $this->handler->handle($event, 'missing-agent', 'moderator');

        $this->assertStringContainsString('chat-1:77', $payload->target->address);
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
