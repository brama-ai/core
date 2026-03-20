<?php

declare(strict_types=1);

namespace App\Tenant;

/**
 * Holds the current tenant for the request lifecycle.
 * Set by TenantContextListener on each request from the session.
 */
final class TenantContext
{
    private ?string $tenantId = null;
    private ?Tenant $tenant = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function set(Tenant $tenant): void
    {
        $this->tenantId = $tenant->getId();
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->tenant = null;
    }

    public function isSet(): bool
    {
        return null !== $this->tenantId;
    }

    public function requireTenantId(): string
    {
        if (null === $this->tenantId) {
            throw new \LogicException('No tenant context set. Ensure TenantContextListener is active.');
        }

        return $this->tenantId;
    }
}
