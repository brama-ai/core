<?php

declare(strict_types=1);

namespace App\AgentRegistry;

use App\Tenant\TenantContext;
use Doctrine\DBAL\Connection;

final class AgentRegistryAuditLogger
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function log(string $agentName, string $action, ?string $actor = null, array $payload = []): void
    {
        $tenantId = $this->tenantContext->isSet() ? $this->tenantContext->getTenantId() : null;

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO agent_registry_audit (agent_name, action, actor, payload, tenant_id, created_at)
            VALUES (:agentName, :action, :actor, :payload, :tenantId, now())
            SQL,
            [
                'agentName' => $agentName,
                'action' => $action,
                'actor' => $actor,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'tenantId' => $tenantId,
            ],
        );
    }
}
