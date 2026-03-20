<?php

declare(strict_types=1);

namespace App\CoderAgent;

use App\CoderAgent\DTO\CreateCoderTaskRequest;

interface CoderTaskRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function create(CreateCoderTaskRequest $request, string $builderTaskPath): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(?TaskStatus $status = null): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findUpdatedSince(\DateTimeImmutable $since): array;

    /**
     * @return array<string, mixed>|null
     */
    public function claimNextQueuedTask(string $workerId): ?array;

    /**
     * @param array<string, mixed>      $stageProgress
     * @param array<string, mixed>|null $compatState
     */
    public function updateRuntimeState(
        string $id,
        TaskStatus $status,
        ?TaskStage $stage,
        array $stageProgress,
        ?string $errorMessage = null,
        ?string $branchName = null,
        ?string $worktreePath = null,
        ?string $summaryPath = null,
        ?string $artifactsPath = null,
        ?string $workerId = null,
        ?\DateTimeImmutable $startedAt = null,
        ?\DateTimeImmutable $finishedAt = null,
        ?array $compatState = null,
    ): void;

    public function queue(string $id): void;

    public function cancel(string $id, ?string $errorMessage = null): void;

    public function retry(string $id): void;

    public function delete(string $id): void;

    public function updatePriority(string $id, int $priority): void;

    /**
     * @return array<string, int>
     */
    public function getStats(): array;
}
