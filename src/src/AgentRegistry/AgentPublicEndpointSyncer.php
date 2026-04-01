<?php

declare(strict_types=1);

namespace App\AgentRegistry;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Syncs public_endpoints from an agent manifest into the agent_public_endpoints table.
 *
 * Uses a full-replace strategy: all existing rows for the agent are deleted and
 * re-inserted from the manifest. This ensures the DB always matches the manifest exactly.
 */
final class AgentPublicEndpointSyncer
{
    public function __construct(
        private readonly AgentPublicEndpointRepositoryInterface $repository,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Sync public endpoints for an agent from its manifest.
     *
     * @param array<string, mixed> $manifest
     */
    public function syncFromManifest(string $agentName, array $manifest): void
    {
        $agentId = $this->resolveAgentId($agentName);

        if (null === $agentId) {
            $this->logger->warning('AgentPublicEndpointSyncer: agent not found in registry', ['agent' => $agentName]);

            return;
        }

        $rawEndpoints = $manifest['public_endpoints'] ?? null;

        if (!is_array($rawEndpoints) || [] === $rawEndpoints) {
            // No endpoints declared — clear any previously stored ones
            $this->repository->syncForAgent($agentId, []);

            return;
        }

        $endpoints = [];

        foreach ($rawEndpoints as $item) {
            if (!is_array($item)) {
                continue;
            }

            $path = isset($item['path']) ? (string) $item['path'] : null;
            $methods = isset($item['methods']) && is_array($item['methods'])
                ? array_values(array_map(strval(...), $item['methods']))
                : null;

            if (null === $path || '' === $path || null === $methods || [] === $methods) {
                continue;
            }

            $endpoints[] = [
                'path' => $path,
                'methods' => $methods,
                'description' => isset($item['description']) ? (string) $item['description'] : null,
            ];
        }

        $this->repository->syncForAgent($agentId, $endpoints);

        $this->logger->info('Agent public endpoints synced', [
            'agent' => $agentName,
            'count' => count($endpoints),
        ]);
    }

    private function resolveAgentId(string $agentName): ?string
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM agent_registry WHERE name = :name',
            ['name' => $agentName],
        );

        return false === $id ? null : (string) $id;
    }
}
