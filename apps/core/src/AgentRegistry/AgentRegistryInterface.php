<?php

declare(strict_types=1);

namespace App\AgentRegistry;

interface AgentRegistryInterface
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function register(array $manifest): void;

    public function enable(string $name, string $enabledBy): bool;

    public function disable(string $name): bool;

    /**
     * @param array<string, mixed> $config
     */
    public function updateConfig(string $name, array $config): bool;

    public function updateHealthStatus(string $name, string $status): void;

    /**
     * Upsert an agent record from the pull discovery cycle.
     * Does not touch enabled/config; preserves health_check_failures managed by health poller.
     *
     * @param array<string, mixed>|null $manifest
     * @param list<string>              $violations
     */
    public function upsertFromDiscovery(string $name, ?array $manifest, string $status, array $violations): void;

    /**
     * Increment health check failure counter. Returns new count.
     */
    public function recordHealthCheckFailure(string $name): int;

    /**
     * Reset health check failure counter and restore status after recovery.
     */
    public function resetHealthCheckFailures(string $name, string $restoredStatus): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findEnabled(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array;

    public function markInstalled(string $name): bool;

    public function markUninstalled(string $name): bool;

    public function delete(string $name): bool;

    /**
     * Hard-delete marketplace agents that were never installed and have accumulated
     * health-check failures equal to or exceeding the given threshold.
     * Returns the number of deleted agents.
     */
    public function deleteStaleMarketplaceAgents(int $failureThreshold): int;
}
