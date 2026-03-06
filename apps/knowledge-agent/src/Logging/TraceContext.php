<?php

declare(strict_types=1);

namespace App\Logging;

final class TraceContext
{
    private string $traceId = '';
    private string $requestId = '';

    public function initialize(?string $incomingTraceId = null): void
    {
        $this->traceId = $incomingTraceId ?? self::generateId();
        $this->requestId = self::generateId();
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    private static function generateId(): string
    {
        $hex = bin2hex(random_bytes(16));

        return \sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
