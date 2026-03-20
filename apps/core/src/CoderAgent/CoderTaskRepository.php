<?php

declare(strict_types=1);

namespace App\CoderAgent;

use App\CoderAgent\DTO\CreateCoderTaskRequest;
use Doctrine\DBAL\Connection;

final class CoderTaskRepository implements CoderTaskRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function create(CreateCoderTaskRequest $request, string $builderTaskPath): array
    {
        $status = $request->queueNow ? TaskStatus::Queued : TaskStatus::Draft;
        $id = (string) $this->connection->fetchOne(
            <<<'SQL'
            INSERT INTO coder_tasks
                (title, description, template_type, priority, status, stage_progress, pipeline_config, compat_state, builder_task_path)
            VALUES
                (:title, :description, :templateType, :priority, :status, :stageProgress, :pipelineConfig, :compatState, :builderTaskPath)
            RETURNING id
            SQL,
            [
                'title' => $request->title,
                'description' => $request->description,
                'templateType' => $request->templateType->value,
                'priority' => $request->priority,
                'status' => $status->value,
                'stageProgress' => json_encode([], JSON_THROW_ON_ERROR),
                'pipelineConfig' => json_encode($request->pipelineConfig, JSON_THROW_ON_ERROR),
                'compatState' => json_encode(['created_by' => $request->createdBy], JSON_THROW_ON_ERROR),
                'builderTaskPath' => $builderTaskPath,
            ],
        );

        $task = $this->findById($id);
        if (null === $task) {
            throw new \RuntimeException('Failed to create coder task.');
        }

        return $task;
    }

    public function findAll(?TaskStatus $status = null): array
    {
        $sql = 'SELECT * FROM coder_tasks';
        $params = [];

        if (null !== $status) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status->value;
        }

        $sql .= ' ORDER BY priority DESC, created_at DESC';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return $rows;
    }

    public function findById(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM coder_tasks WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : $row;
    }

    public function findUpdatedSince(\DateTimeImmutable $since): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM coder_tasks WHERE updated_at > :since ORDER BY updated_at ASC',
            ['since' => $since->format('Y-m-d H:i:sP')],
        );

        return $rows;
    }

    public function claimNextQueuedTask(string $workerId): ?array
    {
        return $this->connection->transactional(function (Connection $connection) use ($workerId): ?array {
            $row = $connection->fetchAssociative(
                <<<'SQL'
                SELECT *
                FROM coder_tasks
                WHERE status = :status
                ORDER BY priority DESC, created_at ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
                SQL,
                ['status' => TaskStatus::Queued->value],
            );

            if (false === $row) {
                return null;
            }

            $connection->executeStatement(
                <<<'SQL'
                UPDATE coder_tasks
                SET status = :newStatus,
                    worker_id = :workerId,
                    started_at = COALESCE(started_at, now()),
                    updated_at = now()
                WHERE id = :id
                SQL,
                [
                    'newStatus' => TaskStatus::InProgress->value,
                    'workerId' => $workerId,
                    'id' => $row['id'],
                ],
            );

            $claimed = $this->findById((string) $row['id']);

            return $claimed;
        });
    }

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
    ): void {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE coder_tasks
            SET status = :status,
                current_stage = :stage,
                stage_progress = :stageProgress,
                error_message = :errorMessage,
                branch_name = COALESCE(:branchName, branch_name),
                worktree_path = COALESCE(:worktreePath, worktree_path),
                summary_path = COALESCE(:summaryPath, summary_path),
                artifacts_path = COALESCE(:artifactsPath, artifacts_path),
                worker_id = :workerId,
                started_at = COALESCE(:startedAt, started_at),
                finished_at = :finishedAt,
                compat_state = COALESCE(:compatState, compat_state),
                updated_at = now()
            WHERE id = :id
            SQL,
            [
                'id' => $id,
                'status' => $status->value,
                'stage' => $stage?->value,
                'stageProgress' => json_encode($stageProgress, JSON_THROW_ON_ERROR),
                'errorMessage' => $errorMessage,
                'branchName' => $branchName,
                'worktreePath' => $worktreePath,
                'summaryPath' => $summaryPath,
                'artifactsPath' => $artifactsPath,
                'workerId' => $workerId,
                'startedAt' => $startedAt?->format('Y-m-d H:i:sP'),
                'finishedAt' => $finishedAt?->format('Y-m-d H:i:sP'),
                'compatState' => null !== $compatState ? json_encode($compatState, JSON_THROW_ON_ERROR) : null,
            ],
        );
    }

    public function queue(string $id): void
    {
        $this->connection->executeStatement(
            'UPDATE coder_tasks SET status = :status, error_message = NULL, finished_at = NULL, updated_at = now() WHERE id = :id',
            ['status' => TaskStatus::Queued->value, 'id' => $id],
        );
    }

    public function cancel(string $id, ?string $errorMessage = null): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE coder_tasks
            SET status = :status,
                error_message = :errorMessage,
                finished_at = now(),
                updated_at = now()
            WHERE id = :id
            SQL,
            ['status' => TaskStatus::Cancelled->value, 'errorMessage' => $errorMessage, 'id' => $id],
        );
    }

    public function retry(string $id): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE coder_tasks
            SET status = :status,
                retry_count = retry_count + 1,
                worker_id = NULL,
                current_stage = NULL,
                error_message = NULL,
                finished_at = NULL,
                updated_at = now()
            WHERE id = :id
            SQL,
            ['status' => TaskStatus::Queued->value, 'id' => $id],
        );
    }

    public function delete(string $id): void
    {
        $this->connection->executeStatement('DELETE FROM coder_tasks WHERE id = :id', ['id' => $id]);
    }

    public function updatePriority(string $id, int $priority): void
    {
        $this->connection->executeStatement(
            "UPDATE coder_tasks SET priority = :priority, updated_at = now() WHERE id = :id AND status IN ('draft', 'queued')",
            [
                'priority' => $priority,
                'id' => $id,
            ],
        );
    }

    public function getStats(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT status, COUNT(*) AS count FROM coder_tasks GROUP BY status',
        );

        $stats = [
            'total' => 0,
            'draft' => 0,
            'queued' => 0,
            'in_progress' => 0,
            'done' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['count'];
            $stats['total'] += $count;
            if (array_key_exists($status, $stats)) {
                $stats[$status] = $count;
            }
        }

        return $stats;
    }
}
