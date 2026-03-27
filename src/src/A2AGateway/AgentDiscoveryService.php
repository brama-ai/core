<?php

declare(strict_types=1);

namespace App\A2AGateway;

use App\A2AGateway\Discovery\AgentDiscoveryProviderInterface;

final class AgentDiscoveryService
{
    public function __construct(
        private readonly AgentDiscoveryProviderInterface $provider,
    ) {
    }

    /**
     * Query Traefik API and return list of agent service descriptors.
     *
     * @return list<array{hostname: string, port: int}>
     */
    public function discoverAgents(): array
    {
        return $this->provider->discover();
    }
}
