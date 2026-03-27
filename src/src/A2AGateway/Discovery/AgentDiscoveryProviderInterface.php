<?php

declare(strict_types=1);

namespace App\A2AGateway\Discovery;

interface AgentDiscoveryProviderInterface
{
    /**
     * @return list<array{hostname: string, port: int}>
     */
    public function discover(): array;
}
