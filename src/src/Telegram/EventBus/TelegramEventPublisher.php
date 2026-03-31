<?php

declare(strict_types=1);

namespace App\Telegram\EventBus;

use App\Channel\EventBus\ChannelEventPublisher;
use App\EventBus\EventBusInterface;
use App\Telegram\DTO\NormalizedEvent;
use Psr\Log\LoggerInterface;

/**
 * @deprecated Use \App\Channel\EventBus\ChannelEventPublisher instead. Will be removed in Phase 5.
 */
final class TelegramEventPublisher
{
    private readonly ChannelEventPublisher $inner;

    public function __construct(
        EventBusInterface $eventBus,
        LoggerInterface $logger,
    ) {
        $this->inner = new ChannelEventPublisher($eventBus, $logger);
    }

    public function publish(NormalizedEvent $event): void
    {
        $this->inner->publish($event);
    }
}
