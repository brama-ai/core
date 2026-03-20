<?php

declare(strict_types=1);

namespace App\CoderAgent;

interface CoderWorkerRepositoryInterface
{
    public function register(string $workerId, int $pid): void;

    public function heartbeat(string $workerId, WorkerStatus $status, ?string $taskId = null, ?string $worktreePath = null): void;

    public function markStopped(string $workerId): void;

    public function requestStop(string $workerId): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $workerId): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findUpdatedSince(\DateTimeImmutable $since): array;

    public function markDeadWorkers(\DateTimeImmutable $olderThan): int;
}
