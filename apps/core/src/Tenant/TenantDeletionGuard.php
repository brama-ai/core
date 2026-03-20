<?php

declare(strict_types=1);

namespace App\Tenant;

final class TenantDeletionGuard
{
    public function __construct(private readonly TenantRepositoryInterface $tenantRepository)
    {
    }

    /**
     * Returns a list of reasons why the tenant cannot be deleted, or an empty array if safe.
     *
     * @return list<string>
     */
    public function check(string $tenantId): array
    {
        $reasons = [];

        $activeAgents = $this->tenantRepository->countActiveAgents($tenantId);
        if ($activeAgents > 0) {
            $reasons[] = sprintf('%d active agent(s) must be uninstalled first.', $activeAgents);
        }

        $enabledJobs = $this->tenantRepository->countEnabledJobs($tenantId);
        if ($enabledJobs > 0) {
            $reasons[] = sprintf('%d enabled scheduled job(s) must be disabled first.', $enabledJobs);
        }

        return $reasons;
    }
}
