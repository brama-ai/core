<?php

declare(strict_types=1);

namespace App\CoderAgent;

interface TaskEventPublisherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $eventType, array $payload): void;
}
