<?php

declare(strict_types=1);

namespace App\A2AGateway;

interface A2AClientInterface
{
    /**
     * Invoke an agent skill via the A2A gateway.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function invoke(string $tool, array $input, string $traceId, string $requestId, string $actor = 'openclaw'): array;
}
