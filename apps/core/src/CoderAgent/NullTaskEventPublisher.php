<?php

declare(strict_types=1);

namespace App\CoderAgent;

final class NullTaskEventPublisher implements TaskEventPublisherInterface
{
    public function publish(string $eventType, array $payload): void
    {
    }
}
