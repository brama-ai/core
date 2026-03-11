<?php

declare(strict_types=1);

namespace App\Scheduler;

interface ScheduledJobRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findDueJobs(): array;

    /**
     * @param array<string, mixed> $payload
     */
    public function registerJob(
        string $agentName,
        string $jobName,
        string $skillId,
        array $payload,
        ?string $cronExpression,
        int $maxRetries,
        int $retryDelaySeconds,
        string $timezone,
        string $nextRunAt,
        string $source = 'manifest',
    ): void;

    public function deleteByAgent(string $agentName): int;

    public function deleteJob(string $id): void;

    public function enableByAgent(string $agentName): int;

    public function disableByAgent(string $agentName): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAgent(string $agentName): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array;

    public function updateAfterRun(string $id, string $status, ?string $nextRunAt): void;

    public function updateRetry(string $id, int $retryCount, string $nextRunAt): void;

    public function disableJob(string $id): void;

    public function triggerNow(string $id): void;

    public function toggleEnabled(string $id, bool $enabled): void;
}
