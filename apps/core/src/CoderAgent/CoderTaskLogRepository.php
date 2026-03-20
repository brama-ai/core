<?php

declare(strict_types=1);

namespace App\CoderAgent;

final class CoderTaskLogRepository implements CoderTaskLogRepositoryInterface
{
    public function __construct(
        private readonly \Doctrine\DBAL\Connection $connection,
    ) {
    }

    public function append(
        string $taskId,
        ?TaskStage $stage,
        string $level,
        string $source,
        string $message,
        ?array $metadata = null,
    ): string {
        $id = $this->connection->fetchOne(
            <<<'SQL'
            INSERT INTO coder_task_logs (task_id, stage, level, source, message, metadata)
            VALUES (:taskId, :stage, :level, :source, :message, :metadata)
            RETURNING id
            SQL,
            [
                'taskId' => $taskId,
                'stage' => $stage?->value,
                'level' => $level,
                'source' => $source,
                'message' => $message,
                'metadata' => null !== $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            ],
        );

        return (string) $id;
    }

    public function findByTask(string $taskId, int $limit = 200, ?string $afterId = null): array
    {
        $sql = 'SELECT * FROM coder_task_logs WHERE task_id = :taskId';
        $params = ['taskId' => $taskId, 'limit' => $limit];

        if (null !== $afterId) {
            $sql .= ' AND id > :afterId';
            $params['afterId'] = $afterId;
        }

        $sql .= ' ORDER BY created_at ASC LIMIT :limit';

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            $sql,
            $params,
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return $rows;
    }

    public function findRecentActivity(int $limit = 20): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM coder_task_logs ORDER BY created_at DESC LIMIT :limit',
            ['limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        return $rows;
    }

    public function findCreatedSince(\DateTimeImmutable $since): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM coder_task_logs WHERE created_at > :since ORDER BY created_at ASC',
            ['since' => $since->format('Y-m-d H:i:sP')],
        );

        return $rows;
    }
}
