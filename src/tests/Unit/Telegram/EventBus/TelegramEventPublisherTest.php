<?php

declare(strict_types=1);

namespace App\Tests\Unit\Telegram\EventBus;

use App\Channel\DTO\NormalizedChat;
use App\Channel\DTO\NormalizedEvent;
use App\Channel\DTO\NormalizedMessage;
use App\Channel\DTO\NormalizedSender;
use App\Channel\EventBus\ChannelEventPublisher;
use App\EventBus\EventBusInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class TelegramEventPublisherTest extends Unit
{
    private EventBusInterface&MockObject $eventBus;
    private LoggerInterface&MockObject $logger;
    private ChannelEventPublisher $publisher;

    protected function setUp(): void
    {
        $this->eventBus = $this->createMock(EventBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->publisher = new ChannelEventPublisher($this->eventBus, $this->logger);
    }

    public function testPublishDispatchesEventToEventBus(): void
    {
        $event = $this->buildEvent('message_created', 'bot-1', 'chat-42', 'trace-abc');

        $this->eventBus->expects($this->once())
            ->method('dispatch')
            ->with('message_created', $this->isArray());

        $this->publisher->publish($event);
    }

    public function testPublishLogsInfoWithCorrectContext(): void
    {
        $event = $this->buildEvent('command_received', 'bot-2', 'chat-99', 'trace-xyz');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                'Publishing channel event to EventBus',
                $this->callback(static function (array $context): bool {
                    return 'command_received' === $context['event_type']
                        && 'bot-2' === $context['bot_id']
                        && 'chat-99' === $context['chat_id']
                        && 'trace-xyz' === $context['trace_id'];
                }),
            );

        $this->publisher->publish($event);
    }

    public function testPublishPassesEventPayloadToDispatch(): void
    {
        $event = $this->buildEvent('message_created', 'bot-1', 'chat-1', 'trace-1');

        $this->eventBus->expects($this->once())
            ->method('dispatch')
            ->with(
                'message_created',
                $this->callback(static function (array $payload): bool {
                    return 'message_created' === $payload['event_type']
                        && 'telegram' === $payload['platform']
                        && 'bot-1' === $payload['bot_id'];
                }),
            );

        $this->publisher->publish($event);
    }

    public function testPublishWorksForDifferentEventTypes(): void
    {
        $eventTypes = ['message_created', 'message_edited', 'member_joined', 'member_left', 'callback_query'];

        foreach ($eventTypes as $eventType) {
            $event = $this->buildEvent($eventType, 'bot-1', 'chat-1', 'trace-1');

            $eventBus = $this->createMock(EventBusInterface::class);
            $eventBus->expects($this->once())
                ->method('dispatch')
                ->with($eventType, $this->isArray());

            $publisher = new ChannelEventPublisher($eventBus, $this->logger);
            $publisher->publish($event);
        }
    }

    private function buildEvent(string $eventType, string $botId, string $chatId, string $traceId): NormalizedEvent
    {
        return new NormalizedEvent(
            eventType: $eventType,
            platform: 'telegram',
            botId: $botId,
            chat: new NormalizedChat(id: $chatId, type: 'group', title: 'Test Chat'),
            sender: new NormalizedSender(id: 'user-1', username: 'testuser'),
            message: new NormalizedMessage(id: 'msg-1', text: 'Hello'),
            traceId: $traceId,
            requestId: 'req-1',
            rawUpdateId: 100,
        );
    }
}
