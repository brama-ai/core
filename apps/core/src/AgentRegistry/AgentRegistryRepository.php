<?php

declare(strict_types=1);

namespace App\AgentRegistry;

use App\Tenant\TenantContext;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class AgentRegistryRepository implements AgentRegistryInterface
{
    private const CACHE_KEY = 'agent_registry.enabled';
    private const CACHE_TTL = 10;

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function register(array $manifest): void
    {
        $name = (string) $manifest['name'];
        $version = (string) $manifest['version'];
        $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR);
        $tenantId = $this->tenantContext->requireTenantId();

        $existing = $this->connection->fetchOne(
            'SELECT id FROM agent_registry WHERE name = :name AND tenant_id = :tenantId',
            ['name' => $name, 'tenantId' => $tenantId],
        );

        if (false === $existing) {
            $this->connection->executeStatement(
                <<<'SQL'
                INSERT INTO agent_registry (name, version, manifest, config, enabled, tenant_id, registered_at, updated_at)
                VALUES (:name, :version, :manifest, '{}', FALSE, :tenantId, now(), now())
                SQL,
                ['name' => $name, 'version' => $version, 'manifest' => $manifestJson, 'tenantId' => $tenantId],
            );
            $this->logger->info('Agent registered', ['agent' => $name, 'version' => $version, 'tenant_id' => $tenantId]);
        } else {
            $this->connection->executeStatement(
                <<<'SQL'
                UPDATE agent_registry
                SET version = :version, manifest = :manifest, updated_at = now()
                WHERE name = :name AND tenant_id = :tenantId
                SQL,
                ['name' => $name, 'version' => $version, 'manifest' => $manifestJson, 'tenantId' => $tenantId],
            );
            $this->logger->info('Agent updated', ['agent' => $name, 'version' => $version, 'tenant_id' => $tenantId]);
        }

        $this->invalidateCache();
    }

    public function enable(string $name, string $enabledBy): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $rows = $this->connection->executeStatement(
            <<<'SQL'
            UPDATE agent_registry
            SET enabled = TRUE, enabled_at = now(), disabled_at = NULL, enabled_by = :enabledBy, updated_at = now()
            WHERE name = :name AND tenant_id = :tenantId
            SQL,
            ['name' => $name, 'enabledBy' => $enabledBy, 'tenantId' => $tenantId],
        );

        if ($rows > 0) {
            $this->logger->info('Agent enabled', ['agent' => $name, 'enabled_by' => $enabledBy, 'tenant_id' => $tenantId]);
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    public function disable(string $name): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $rows = $this->connection->executeStatement(
            <<<'SQL'
            UPDATE agent_registry
            SET enabled = FALSE, disabled_at = now(), updated_at = now()
            WHERE name = :name AND tenant_id = :tenantId
            SQL,
            ['name' => $name, 'tenantId' => $tenantId],
        );

        if ($rows > 0) {
            $this->logger->info('Agent disabled', ['agent' => $name, 'tenant_id' => $tenantId]);
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function updateConfig(string $name, array $config): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $rows = $this->connection->executeStatement(
            <<<'SQL'
            UPDATE agent_registry SET config = :config, updated_at = now() WHERE name = :name AND tenant_id = :tenantId
            SQL,
            ['name' => $name, 'config' => json_encode($config, JSON_THROW_ON_ERROR), 'tenantId' => $tenantId],
        );

        if ($rows > 0) {
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    public function updateHealthStatus(string $name, string $status): void
    {
        // Health status updates run globally (from health poller), no tenant scoping needed
        $this->connection->executeStatement(
            'UPDATE agent_registry SET health_status = :status WHERE name = :name',
            ['name' => $name, 'status' => $status],
        );
    }

    /**
     * Upsert from discovery runs globally — agents self-register without tenant context.
     * They get assigned to the default tenant until explicitly claimed.
     *
     * @param array<string, mixed>|null $manifest
     * @param list<string>              $violations
     */
    public function upsertFromDiscovery(string $name, ?array $manifest, string $status, array $violations): void
    {
        $version = is_string($manifest['version'] ?? null) ? (string) $manifest['version'] : '0.0.0';
        $manifestJson = null !== $manifest ? json_encode($manifest, JSON_THROW_ON_ERROR) : '{}';
        $violationsJson = json_encode($violations, JSON_THROW_ON_ERROR);
        $tenantId = $this->tenantContext->isSet() ? $this->tenantContext->requireTenantId() : $this->getDefaultTenantId();

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO agent_registry (name, version, manifest, config, enabled, health_status, violations, tenant_id, registered_at, updated_at)
            VALUES (:name, :version, :manifest, '{}', FALSE, :status, :violations, :tenantId, now(), now())
            ON CONFLICT (name, tenant_id) DO UPDATE SET
                version       = EXCLUDED.version,
                manifest      = EXCLUDED.manifest,
                health_status = EXCLUDED.health_status,
                violations    = EXCLUDED.violations,
                updated_at    = now()
            SQL,
            [
                'name' => $name,
                'version' => $version,
                'manifest' => $manifestJson,
                'status' => $status,
                'violations' => $violationsJson,
                'tenantId' => $tenantId,
            ],
        );

        $this->invalidateCache();
    }

    public function recordHealthCheckFailure(string $name): int
    {
        // Health checks run globally
        $this->connection->executeStatement(
            'UPDATE agent_registry SET health_check_failures = health_check_failures + 1 WHERE name = :name',
            ['name' => $name],
        );

        $count = $this->connection->fetchOne(
            'SELECT health_check_failures FROM agent_registry WHERE name = :name',
            ['name' => $name],
        );

        return is_numeric($count) ? (int) $count : 1;
    }

    public function resetHealthCheckFailures(string $name, string $restoredStatus): void
    {
        $this->connection->executeStatement(
            'UPDATE agent_registry SET health_check_failures = 0, health_status = :status WHERE name = :name',
            ['name' => $name, 'status' => $restoredStatus],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        if ($this->tenantContext->isSet()) {
            return $this->connection->fetchAllAssociative(
                'SELECT * FROM agent_registry WHERE tenant_id = :tenantId ORDER BY name',
                ['tenantId' => $this->tenantContext->requireTenantId()],
            );
        }

        return $this->connection->fetchAllAssociative(
            'SELECT * FROM agent_registry ORDER BY name',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findEnabled(): array
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        if ($item->isHit()) {
            /** @var list<array<string, mixed>> $cached */
            $cached = $item->get();

            return $cached;
        }

        // Enabled agents are loaded globally for the scheduler and A2A gateway
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM agent_registry WHERE enabled = TRUE ORDER BY name',
        );

        $item->set($rows);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        if ($this->tenantContext->isSet()) {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM agent_registry WHERE name = :name AND tenant_id = :tenantId',
                ['name' => $name, 'tenantId' => $this->tenantContext->requireTenantId()],
            );
        } else {
            $row = $this->connection->fetchAssociative(
                'SELECT * FROM agent_registry WHERE name = :name',
                ['name' => $name],
            );
        }

        return false === $row ? null : $row;
    }

    public function markInstalled(string $name): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $rows = $this->connection->executeStatement(
            'UPDATE agent_registry SET installed_at = now(), updated_at = now() WHERE name = :name AND tenant_id = :tenantId',
            ['name' => $name, 'tenantId' => $tenantId],
        );

        if ($rows > 0) {
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    public function markUninstalled(string $name): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $rows = $this->connection->executeStatement(
            <<<'SQL'
            UPDATE agent_registry
            SET installed_at = NULL,
                enabled = FALSE,
                disabled_at = now(),
                enabled_by = NULL,
                config = '{}',
                updated_at = now()
            WHERE name = :name AND tenant_id = :tenantId
            SQL,
            ['name' => $name, 'tenantId' => $tenantId],
        );

        if ($rows > 0) {
            $this->logger->info('Agent marked uninstalled', ['agent' => $name, 'tenant_id' => $tenantId]);
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    public function delete(string $name): bool
    {
        $tenantId = $this->tenantContext->requireTenantId();

        $rows = $this->connection->executeStatement(
            'DELETE FROM agent_registry WHERE name = :name AND tenant_id = :tenantId',
            ['name' => $name, 'tenantId' => $tenantId],
        );

        if ($rows > 0) {
            $this->logger->info('Agent deleted', ['agent' => $name, 'tenant_id' => $tenantId]);
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    public function deleteStaleMarketplaceAgents(int $failureThreshold): int
    {
        // Stale cleanup runs globally across all tenants
        /** @var list<array<string, mixed>> $stale */
        $stale = $this->connection->fetchAllAssociative(
            'SELECT name, tenant_id FROM agent_registry WHERE installed_at IS NULL AND health_check_failures >= :threshold',
            ['threshold' => $failureThreshold],
        );

        if ([] === $stale) {
            return 0;
        }

        $deleted = 0;

        foreach ($stale as $agent) {
            $name = (string) $agent['name'];
            $agentTenantId = (string) $agent['tenant_id'];

            $rows = $this->connection->executeStatement(
                'DELETE FROM agent_registry WHERE name = :name AND tenant_id = :tenantId AND installed_at IS NULL AND health_check_failures >= :threshold',
                ['name' => $name, 'tenantId' => $agentTenantId, 'threshold' => $failureThreshold],
            );

            if ($rows > 0) {
                $this->connection->executeStatement(
                    <<<'SQL'
                    INSERT INTO agent_registry_audit (agent_name, action, actor, payload, tenant_id, created_at)
                    VALUES (:agentName, 'stale_deleted', NULL, '{}', :tenantId, now())
                    SQL,
                    ['agentName' => $name, 'tenantId' => $agentTenantId],
                );

                $this->logger->info('Stale marketplace agent auto-deleted', ['agent' => $name, 'failure_threshold' => $failureThreshold, 'tenant_id' => $agentTenantId]);
                ++$deleted;
            }
        }

        if ($deleted > 0) {
            $this->invalidateCache();
        }

        return $deleted;
    }

    /**
     * Check if a non-shared agent is already installed in another tenant.
     */
    public function isAgentInstalledInOtherTenant(string $name, string $currentTenantId): bool
    {
        $count = $this->connection->fetchOne(
            <<<'SQL'
            SELECT COUNT(*) FROM agent_registry
            WHERE name = :name AND tenant_id != :tenantId AND installed_at IS NOT NULL AND shared = FALSE
            SQL,
            ['name' => $name, 'tenantId' => $currentTenantId],
        );

        return (int) $count > 0;
    }

    private function invalidateCache(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }

    private function getDefaultTenantId(): string
    {
        return '00000000-0000-4000-a000-000000000001';
    }
}
