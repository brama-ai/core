<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\Command\Handler\AgentsListHandler;
use App\Channel\DTO\NormalizedChat;
use App\Channel\DTO\NormalizedEvent;
use App\Channel\DTO\NormalizedMessage;
use App\Channel\DTO\NormalizedSender;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class AgentsListHandlerTest extends Unit
{
    private AgentRegistryInterface&MockObject $agentRegistry;
    private AgentsListHandler $handler;

    protected function setUp(): void
    {
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->handler = new AgentsListHandler($this->agentRegistry);
    }

    public function testHandleRepliesWithNoAgentsMessageWhenEmpty(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('Агентів не зареєстровано', $payload->text);
    }

    public function testHandleListsEnabledAgentWithGreenIcon(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([
                [
                    'name' => 'my-agent',
                    'enabled' => true,
                    'manifest' => json_encode(['description' => 'My agent description']),
                ],
            ]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('🟢', $payload->text);
        $this->assertStringContainsString('my-agent', $payload->text);
        $this->assertStringContainsString('My agent description', $payload->text);
    }

    public function testHandleListsDisabledAgentWithRedIcon(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([
                [
                    'name' => 'disabled-agent',
                    'enabled' => false,
                    'manifest' => json_encode(['description' => 'Disabled agent']),
                ],
            ]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('🔴', $payload->text);
        $this->assertStringContainsString('disabled-agent', $payload->text);
    }

    public function testHandleIncludesThreadIdInTargetAddressWhenPresent(): void
    {
        $event = $this->buildEvent('chat-1', '55');

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('chat-1:55', $payload->target->address);
    }

    public function testHandleListsMultipleAgents(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([
                ['name' => 'agent-a', 'enabled' => true, 'manifest' => json_encode(['description' => 'Agent A'])],
                ['name' => 'agent-b', 'enabled' => false, 'manifest' => json_encode(['description' => 'Agent B'])],
            ]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('agent-a', $payload->text);
        $this->assertStringContainsString('agent-b', $payload->text);
        $this->assertStringContainsString('🟢', $payload->text);
        $this->assertStringContainsString('🔴', $payload->text);
    }

    public function testHandleWithManifestAsArray(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([
                [
                    'name' => 'array-agent',
                    'enabled' => true,
                    'manifest' => ['description' => 'Array manifest'],
                ],
            ]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('array-agent', $payload->text);
    }

    private function buildEvent(string $chatId, ?string $threadId): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: $chatId, type: 'group', threadId: $threadId),
            sender: new NormalizedSender(id: 'user-1'),
            message: new NormalizedMessage(id: 'msg-1', commandName: '/agents'),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }
}
