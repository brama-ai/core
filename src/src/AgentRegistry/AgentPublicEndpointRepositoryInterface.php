<?php

declare(strict_types=1);

namespace App\AgentRegistry;

interface AgentPublicEndpointRepositoryInterface
{
    /**
     * Replace all public endpoints for an agent (full sync).
     *
     * @param list<array{path: string, methods: list<string>, description?: string|null}> $endpoints
     */
    public function syncForAgent(string $agentId, array $endpoints): void;

    /**
     * Find a public endpoint by agent name and path.
     */
    public function findByAgentNameAndPath(string $agentName, string $path): ?AgentPublicEndpoint;

    /**
     * Find all public endpoints for an agent by name.
     *
     * @return list<AgentPublicEndpoint>
     */
    public function findByAgentName(string $agentName): array;
}
