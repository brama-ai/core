<?php

declare(strict_types=1);

namespace App\CoderAgent;

interface CoderTaskLogRepositoryInterface
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function append(
        string $taskId,
        ?TaskStage $stage,
        string $level,
        string $source,
        string $message,
        ?array $metadata = null,
    ): string;

    /**
     * @return list<array<string, mixed>>
     */
    public function findByTask(string $taskId, int $limit = 200, ?string $afterId = null): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecentActivity(int $limit = 20): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function findCreatedSince(\DateTimeImmutable $since): array;
}
