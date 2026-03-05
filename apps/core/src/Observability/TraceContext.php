<?php

declare(strict_types=1);

namespace App\Observability;

final class TraceContext
{
    public static function normalizeTraceId(string $traceId): string
    {
        $hexOnly = strtolower((string) preg_replace('/[^a-f0-9]/i', '', $traceId));
        if (strlen($hexOnly) >= 32) {
            return substr($hexOnly, 0, 32);
        }

        $seed = '' !== $traceId ? $traceId : uniqid('trace_', true);

        return substr(hash('sha256', $seed), 0, 32);
    }

    public static function generateSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public static function buildTraceparent(string $traceId): string
    {
        return sprintf('00-%s-%s-01', self::normalizeTraceId($traceId), self::generateSpanId());
    }
}
