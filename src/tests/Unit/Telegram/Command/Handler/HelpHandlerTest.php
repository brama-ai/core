<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\Command\Handler\HelpHandler;
use App\Channel\DTO\NormalizedChat;
use App\Channel\DTO\NormalizedEvent;
use App\Channel\DTO\NormalizedMessage;
use App\Channel\DTO\NormalizedSender;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class HelpHandlerTest extends Unit
{
    private AgentRegistryInterface&MockObject $agentRegistry;
    private HelpHandler $handler;

    protected function setUp(): void
    {
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->handler = new HelpHandler($this->agentRegistry);
    }

    public function testHandleSendsHelpTextWithBuiltInCommands(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('/help', $payload->text);
        $this->assertStringContainsString('/agents', $payload->text);
        $this->assertStringContainsString('/agent enable', $payload->text);
        $this->assertStringContainsString('/agent disable', $payload->text);
    }

    public function testHandleIncludesAgentCommandsInHelpText(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([
                [
                    'name' => 'knowledge-agent',
                    'manifest' => json_encode([
                        'commands' => ['/search', '/ask'],
                        'description' => 'Knowledge base agent',
                    ]),
                ],
            ]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('/search', $payload->text);
        $this->assertStringContainsString('/ask', $payload->text);
        $this->assertStringContainsString('Knowledge base agent', $payload->text);
    }

    public function testHandleIncludesThreadIdInTargetAddressWhenPresent(): void
    {
        $event = $this->buildEvent('chat-1', '99');

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('chat-1:99', $payload->target->address);
    }

    public function testHandleUsesAgentNameAsDescriptionFallback(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([
                [
                    'name' => 'my-agent',
                    'manifest' => json_encode([
                        'commands' => ['/mycommand'],
                    ]),
                ],
            ]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('my-agent', $payload->text);
    }

    public function testHandleWithManifestAsArray(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([
                [
                    'name' => 'array-agent',
                    'manifest' => [
                        'commands' => ['/cmd'],
                        'description' => 'Array manifest agent',
                    ],
                ],
            ]);

        $payload = $this->handler->handle($event);

        $this->assertStringContainsString('/cmd', $payload->text);
    }

    private function buildEvent(string $chatId, ?string $threadId): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: 'command_received',
            platform: 'telegram',
            botId: 'bot-1',
            chat: new NormalizedChat(id: $chatId, type: 'group', threadId: $threadId),
            sender: new NormalizedSender(id: 'user-1'),
            message: new NormalizedMessage(id: 'msg-1', commandName: '/help'),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }
}
