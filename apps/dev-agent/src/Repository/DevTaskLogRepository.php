<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

class DevTaskLogRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function append(int $taskId, ?string $agentStep, string $level, string $message): void
    {
        $this->connection->executeStatement(
            'INSERT INTO dev_task_logs (task_id, agent_step, level, message) VALUES (:task_id, :agent_step, :level, :message)',
            [
                'task_id' => $taskId,
                'agent_step' => $agentStep,
                'level' => $level,
                'message' => $message,
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByTaskId(int $taskId, ?int $afterId = null): array
    {
        $sql = 'SELECT * FROM dev_task_logs WHERE task_id = :task_id';
        $params = ['task_id' => $taskId];

        if (null !== $afterId && $afterId > 0) {
            $sql .= ' AND id > :after_id';
            $params['after_id'] = $afterId;
        }

        $sql .= ' ORDER BY created_at ASC, id ASC LIMIT 500';

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function countByTaskId(int $taskId): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM dev_task_logs WHERE task_id = :task_id',
            ['task_id' => $taskId],
        );

        return (int) $result;
    }
}
