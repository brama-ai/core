<?php

declare(strict_types=1);

namespace App\CoderAgent;

final class CoderWorkerRepository implements CoderWorkerRepositoryInterface
{
    public function __construct(
        private readonly \Doctrine\DBAL\Connection $connection,
    ) {
    }

    public function register(string $workerId, int $pid): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO coder_workers (id, status, pid, started_at, last_heartbeat_at)
            VALUES (:id, :status, :pid, now(), now())
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                pid = EXCLUDED.pid,
                started_at = now(),
                last_heartbeat_at = now()
            SQL,
            ['id' => $workerId, 'status' => WorkerStatus::Idle->value, 'pid' => $pid],
        );
    }

    public function heartbeat(string $workerId, WorkerStatus $status, ?string $taskId = null, ?string $worktreePath = null): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE coder_workers
            SET status = :status,
                current_task_id = :taskId,
                worktree_path = :worktreePath,
                last_heartbeat_at = now()
            WHERE id = :id
            SQL,
            [
                'id' => $workerId,
                'status' => $status->value,
                'taskId' => $taskId,
                'worktreePath' => $worktreePath,
            ],
        );
    }

    public function markStopped(string $workerId): void
    {
        $this->connection->executeStatement(
            'UPDATE coder_workers SET status = :status, current_task_id = NULL, worktree_path = NULL, last_heartbeat_at = now() WHERE id = :id',
            ['status' => WorkerStatus::Stopped->value, 'id' => $workerId],
        );
    }

    public function requestStop(string $workerId): void
    {
        $this->connection->executeStatement(
            'UPDATE coder_workers SET status = :status, last_heartbeat_at = now() WHERE id = :id',
            ['status' => WorkerStatus::Stopping->value, 'id' => $workerId],
        );
    }

    public function findAll(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM coder_workers ORDER BY id ASC');

        return $rows;
    }

    public function findById(string $workerId): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM coder_workers WHERE id = :id', ['id' => $workerId]);

        return false === $row ? null : $row;
    }

    public function findUpdatedSince(\DateTimeImmutable $since): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM coder_workers WHERE last_heartbeat_at > :since ORDER BY last_heartbeat_at ASC',
            ['since' => $since->format('Y-m-d H:i:sP')],
        );

        return $rows;
    }

    public function markDeadWorkers(\DateTimeImmutable $olderThan): int
    {
        return (int) $this->connection->executeStatement(
            "UPDATE coder_workers SET status = :status WHERE status IN ('idle', 'busy', 'stopping') AND last_heartbeat_at < :olderThan",
            [
                'status' => WorkerStatus::Dead->value,
                'olderThan' => $olderThan->format('Y-m-d H:i:sP'),
            ],
        );
    }
}
