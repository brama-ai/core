<?php

declare(strict_types=1);

namespace App\AgentRegistry;

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

        $existing = $this->connection->fetchOne(
            'SELECT id FROM agent_registry WHERE name = :name',
            ['name' => $name],
        );

        if (false === $existing) {
            $this->connection->executeStatement(
                <<<'SQL'
                INSERT INTO agent_registry (name, version, manifest, config, enabled, registered_at, updated_at)
                VALUES (:name, :version, :manifest, '{}', FALSE, now(), now())
                SQL,
                ['name' => $name, 'version' => $version, 'manifest' => $manifestJson],
            );
            $this->logger->info('Agent registered', ['agent' => $name, 'version' => $version]);
        } else {
            $this->connection->executeStatement(
                <<<'SQL'
                UPDATE agent_registry
                SET version = :version, manifest = :manifest, updated_at = now()
                WHERE name = :name
                SQL,
                ['name' => $name, 'version' => $version, 'manifest' => $manifestJson],
            );
            $this->logger->info('Agent updated', ['agent' => $name, 'version' => $version]);
        }

        $this->invalidateCache();
    }

    public function enable(string $name, string $enabledBy): bool
    {
        $rows = $this->connection->executeStatement(
            <<<'SQL'
            UPDATE agent_registry
            SET enabled = TRUE, enabled_at = now(), disabled_at = NULL, enabled_by = :enabledBy, updated_at = now()
            WHERE name = :name
            SQL,
            ['name' => $name, 'enabledBy' => $enabledBy],
        );

        if ($rows > 0) {
            $this->logger->info('Agent enabled', ['agent' => $name, 'enabled_by' => $enabledBy]);
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    public function disable(string $name): bool
    {
        $rows = $this->connection->executeStatement(
            <<<'SQL'
            UPDATE agent_registry
            SET enabled = FALSE, disabled_at = now(), updated_at = now()
            WHERE name = :name
            SQL,
            ['name' => $name],
        );

        if ($rows > 0) {
            $this->logger->info('Agent disabled', ['agent' => $name]);
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function updateConfig(string $name, array $config): bool
    {
        $rows = $this->connection->executeStatement(
            <<<'SQL'
            UPDATE agent_registry SET config = :config, updated_at = now() WHERE name = :name
            SQL,
            ['name' => $name, 'config' => json_encode($config, JSON_THROW_ON_ERROR)],
        );

        if ($rows > 0) {
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    public function updateHealthStatus(string $name, string $status): void
    {
        $this->connection->executeStatement(
            'UPDATE agent_registry SET health_status = :status WHERE name = :name',
            ['name' => $name, 'status' => $status],
        );
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @param list<string>              $violations
     */
    public function upsertFromDiscovery(string $name, ?array $manifest, string $status, array $violations): void
    {
        $version = is_string($manifest['version'] ?? null) ? (string) $manifest['version'] : '0.0.0';
        $manifestJson = null !== $manifest ? json_encode($manifest, JSON_THROW_ON_ERROR) : '{}';
        $violationsJson = json_encode($violations, JSON_THROW_ON_ERROR);

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO agent_registry (name, version, manifest, config, enabled, health_status, violations, registered_at, updated_at)
            VALUES (:name, :version, :manifest, '{}', FALSE, :status, :violations, now(), now())
            ON CONFLICT (name) DO UPDATE SET
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
            ],
        );

        $this->invalidateCache();
    }

    public function recordHealthCheckFailure(string $name): int
    {
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
        /* @var list<array<string, mixed>> */
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
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM agent_registry WHERE name = :name',
            ['name' => $name],
        );

        return false === $row ? null : $row;
    }

    public function markInstalled(string $name): bool
    {
        $rows = $this->connection->executeStatement(
            'UPDATE agent_registry SET installed_at = now(), updated_at = now() WHERE name = :name',
            ['name' => $name],
        );

        if ($rows > 0) {
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    public function delete(string $name): bool
    {
        $rows = $this->connection->executeStatement(
            'DELETE FROM agent_registry WHERE name = :name',
            ['name' => $name],
        );

        if ($rows > 0) {
            $this->logger->info('Agent deleted', ['agent' => $name]);
            $this->invalidateCache();
        }

        return $rows > 0;
    }

    private function invalidateCache(): void
    {
        $this->cache->deleteItem(self::CACHE_KEY);
    }
}
