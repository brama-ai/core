<?php

declare(strict_types=1);

namespace App\Logging;

final class TraceEvent
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public static function build(
        string $eventName,
        string $step,
        string $sourceApp,
        string $status,
        array $context = [],
    ): array {
        return array_merge([
            'event_name' => $eventName,
            'step' => $step,
            'source_app' => $sourceApp,
            'status' => $status,
            'sequence_order' => (int) round(microtime(true) * 1000000),
        ], $context);
    }
}
