<?php

declare(strict_types=1);

namespace App\Tenant;

interface TenantRepositoryInterface
{
    public function findById(string $id): ?Tenant;

    public function findBySlug(string $slug): ?Tenant;

    /**
     * @return list<Tenant>
     */
    public function findAll(): array;

    /**
     * @return list<Tenant>
     */
    public function findByUser(string $userUuid): array;

    public function create(string $name, string $slug): string;

    public function update(string $id, string $name, bool $enabled): bool;

    public function delete(string $id): bool;

    public function assignUser(string $tenantId, string $userUuid, string $role = 'member'): void;

    /**
     * @return array<string, int> tenant_id => member count
     */
    public function countMembersAll(): array;

    public function countActiveAgents(string $tenantId): int;

    public function countEnabledJobs(string $tenantId): int;
}
