<?php

declare(strict_types=1);

namespace App\Channel\EventBus;

use App\Channel\DTO\NormalizedEvent;
use App\EventBus\EventBusInterface;
use Psr\Log\LoggerInterface;

final class ChannelEventPublisher
{
    public function __construct(
        private readonly EventBusInterface $eventBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(NormalizedEvent $event): void
    {
        $this->logger->info('Publishing channel event to EventBus', [
            'event_type' => $event->eventType,
            'platform' => $event->platform,
            'bot_id' => $event->botId,
            'chat_id' => $event->chat->id,
            'trace_id' => $event->traceId,
        ]);

        $this->eventBus->dispatch($event->eventType, $event->toArray());
    }
}
