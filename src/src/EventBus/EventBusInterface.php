<?php

declare(strict_types=1);

namespace App\EventBus;

interface EventBusInterface
{
    /**
     * Dispatch a platform event to all enabled agents subscribed to it.
     *
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $eventType, array $payload): void;
}
