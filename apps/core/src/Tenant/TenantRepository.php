<?php

declare(strict_types=1);

namespace App\Tenant;

use Doctrine\DBAL\Connection;

final class TenantRepository implements TenantRepositoryInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findById(string $id): ?Tenant
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM tenants WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    public function findBySlug(string $slug): ?Tenant
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM tenants WHERE slug = :slug',
            ['slug' => $slug],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * @return list<Tenant>
     */
    public function findAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM tenants ORDER BY name',
        );

        return array_map($this->hydrate(...), $rows);
    }

    /**
     * @return list<Tenant>
     */
    public function findByUser(string $userUuid): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT t.* FROM tenants t
            INNER JOIN user_tenant ut ON ut.tenant_id = t.id
            WHERE ut.user_id = :userUuid
            ORDER BY t.name
            SQL,
            ['userUuid' => $userUuid],
        );

        return array_map($this->hydrate(...), $rows);
    }

    public function create(string $name, string $slug): string
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO tenants (name, slug, enabled, created_at, updated_at)
            VALUES (:name, :slug, TRUE, now(), now())
            SQL,
            ['name' => $name, 'slug' => $slug],
        );

        $id = $this->connection->fetchOne(
            'SELECT id FROM tenants WHERE slug = :slug',
            ['slug' => $slug],
        );

        return (string) $id;
    }

    public function update(string $id, string $name, bool $enabled): bool
    {
        $rows = $this->connection->executeStatement(
            'UPDATE tenants SET name = :name, enabled = :enabled, updated_at = now() WHERE id = :id',
            ['id' => $id, 'name' => $name, 'enabled' => $enabled ? 'true' : 'false'],
        );

        return $rows > 0;
    }

    public function delete(string $id): bool
    {
        $rows = $this->connection->executeStatement(
            'DELETE FROM tenants WHERE id = :id',
            ['id' => $id],
        );

        return $rows > 0;
    }

    public function assignUser(string $tenantId, string $userUuid, string $role = 'member'): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO user_tenant (user_id, tenant_id, role, joined_at)
            VALUES (:userUuid, :tenantId, :role, now())
            ON CONFLICT (user_id, tenant_id) DO UPDATE SET role = EXCLUDED.role
            SQL,
            ['userUuid' => $userUuid, 'tenantId' => $tenantId, 'role' => $role],
        );
    }

    /**
     * @return array<string, int>
     */
    public function countMembersAll(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT tenant_id, COUNT(*) AS cnt FROM user_tenant GROUP BY tenant_id',
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['tenant_id']] = (int) $row['cnt'];
        }

        return $result;
    }

    public function countActiveAgents(string $tenantId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM agent_registry WHERE tenant_id = :tenantId AND enabled = TRUE',
            ['tenantId' => $tenantId],
        );

        return (int) $count;
    }

    public function countEnabledJobs(string $tenantId): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM scheduled_jobs WHERE tenant_id = :tenantId AND enabled = TRUE',
            ['tenantId' => $tenantId],
        );

        return (int) $count;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Tenant
    {
        return new Tenant(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            (bool) $row['enabled'],
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
