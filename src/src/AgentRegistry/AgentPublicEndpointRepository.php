<?php

declare(strict_types=1);

namespace App\AgentRegistry;

use Doctrine\DBAL\Connection;

final class AgentPublicEndpointRepository implements AgentPublicEndpointRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Replace all public endpoints for an agent (full sync).
     *
     * @param list<array{path: string, methods: list<string>, description?: string|null}> $endpoints
     */
    public function syncForAgent(string $agentId, array $endpoints): void
    {
        $this->connection->executeStatement(
            'DELETE FROM agent_public_endpoints WHERE agent_id = :agentId',
            ['agentId' => $agentId],
        );

        foreach ($endpoints as $endpoint) {
            $this->connection->executeStatement(
                <<<'SQL'
                INSERT INTO agent_public_endpoints (agent_id, path, methods, description, created_at, updated_at)
                VALUES (:agentId, :path, :methods, :description, now(), now())
                ON CONFLICT (agent_id, path) DO UPDATE SET
                    methods     = EXCLUDED.methods,
                    description = EXCLUDED.description,
                    updated_at  = now()
                SQL,
                [
                    'agentId' => $agentId,
                    'path' => $endpoint['path'],
                    'methods' => json_encode($endpoint['methods'], JSON_THROW_ON_ERROR),
                    'description' => $endpoint['description'] ?? null,
                ],
            );
        }
    }

    /**
     * Find a public endpoint by agent name and path.
     */
    public function findByAgentNameAndPath(string $agentName, string $path): ?AgentPublicEndpoint
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
            SELECT ape.*, ar.name AS agent_name
            FROM agent_public_endpoints ape
            JOIN agent_registry ar ON ar.id = ape.agent_id
            WHERE ar.name = :agentName AND ape.path = :path
            SQL,
            ['agentName' => $agentName, 'path' => $path],
        );

        if (false === $row) {
            return null;
        }

        return AgentPublicEndpoint::fromRow($row);
    }

    /**
     * Find all public endpoints for an agent by name.
     *
     * @return list<AgentPublicEndpoint>
     */
    public function findByAgentName(string $agentName): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT ape.*, ar.name AS agent_name
            FROM agent_public_endpoints ape
            JOIN agent_registry ar ON ar.id = ape.agent_id
            WHERE ar.name = :agentName
            ORDER BY ape.path
            SQL,
            ['agentName' => $agentName],
        );

        return array_map(AgentPublicEndpoint::fromRow(...), $rows);
    }
}
