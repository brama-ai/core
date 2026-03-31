<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Telegram\Command\Handler\HelpHandler;
use App\Telegram\DTO\NormalizedChat;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\DTO\NormalizedMessage;
use App\Telegram\DTO\NormalizedSender;
use App\Telegram\Service\TelegramSenderInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class HelpHandlerTest extends Unit
{
    private AgentRegistryInterface&MockObject $agentRegistry;
    private TelegramSenderInterface&MockObject $sender;
    private HelpHandler $handler;

    protected function setUp(): void
    {
        $this->agentRegistry = $this->createMock(AgentRegistryInterface::class);
        $this->sender = $this->createMock(TelegramSenderInterface::class);
        $this->handler = new HelpHandler($this->agentRegistry);
    }

    public function testHandleSendsHelpTextWithBuiltInCommands(): void
    {
        $event = $this->buildEvent('chat-1', null);

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->callback(static function (string $text): bool {
                    return str_contains($text, '/help')
                        && str_contains($text, '/agents')
                        && str_contains($text, '/agent enable')
                        && str_contains($text, '/agent disable');
                }),
                [],
            );

        $this->handler->handle($event, $this->sender);
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

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->callback(static function (string $text): bool {
                    return str_contains($text, '/search')
                        && str_contains($text, '/ask')
                        && str_contains($text, 'Knowledge base agent');
                }),
                [],
            );

        $this->handler->handle($event, $this->sender);
    }

    public function testHandleIncludesThreadIdInOptionsWhenPresent(): void
    {
        $event = $this->buildEvent('chat-1', '99');

        $this->agentRegistry->expects($this->once())
            ->method('findEnabled')
            ->willReturn([]);

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->isString(),
                ['thread_id' => '99'],
            );

        $this->handler->handle($event, $this->sender);
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

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->callback(static fn (string $text): bool => str_contains($text, 'my-agent')),
                [],
            );

        $this->handler->handle($event, $this->sender);
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

        $this->sender->expects($this->once())
            ->method('send')
            ->with(
                'bot-1',
                'chat-1',
                $this->callback(static fn (string $text): bool => str_contains($text, '/cmd')),
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
            message: new NormalizedMessage(id: 'msg-1', commandName: '/help'),
            traceId: 'trace-1',
            requestId: 'req-1',
            rawUpdateId: 1,
        );
    }
}
