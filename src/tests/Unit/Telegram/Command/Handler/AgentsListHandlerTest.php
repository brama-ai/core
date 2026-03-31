<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Telegram\Command\Handler\AgentsListHandler;
use App\Telegram\DTO\NormalizedChat;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\DTO\NormalizedMessage;
use App\Telegram\DTO\NormalizedSender;
use App\Telegram\Service\TelegramSenderInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class AgentsListHandlerTest extends Unit
{
    private AgentRegistryInterface&MockObject $agentRegistry;
    private TelegramSenderInterface&MockObject $sender;
    private AgentsListHandler $handler;

    protected function setUp(): void
    {
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->sender = $this->createMock(TelegramSenderInterface::class);
        $this->handler = new AgentsListHandler($this->agentRegistry);
    }

    public function testHandleRepliesWithNoAgentsMessageWhenEmpty(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->stringContains('Агентів не зареєстровано'), []);

        $this->handler->handle($event, $this->sender);
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

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->callback(static function (string $text): bool {
                    return str_contains($text, '🟢')
                        && str_contains($text, 'my-agent')
                        && str_contains($text, 'My agent description');
                }),
                [],
            );

        $this->handler->handle($event, $this->sender);
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

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->callback(static fn (string $text): bool => str_contains($text, '🔴') && str_contains($text, 'disabled-agent')),
                [],
            );

        $this->handler->handle($event, $this->sender);
    }

    public function testHandleIncludesThreadIdInOptionsWhenPresent(): void
    {
        $event = $this->buildEvent('chat-1', '55');

        $this->agentRegistry->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with('bot-1', 'chat-1', $this->isString(), ['thread_id' => '55']);

        $this->handler->handle($event, $this->sender);
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

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->callback(static function (string $text): bool {
                    return str_contains($text, 'agent-a')
                        && str_contains($text, 'agent-b')
                        && str_contains($text, '🟢')
                        && str_contains($text, '🔴');
                }),
                [],
            );

        $this->handler->handle($event, $this->sender);
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

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->callback(static fn (string $text): bool => str_contains($text, 'array-agent')),
                [],
            );

        $this->handler->handle($event, $this->sender);
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
