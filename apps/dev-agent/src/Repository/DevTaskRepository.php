<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

class DevTaskRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function insert(string $title, string $description, string $pipelineOptions = '{}'): int
    {
        $this->connection->executeStatement(
            'INSERT INTO dev_tasks (title, description, pipeline_options) VALUES (:title, :description, :options)',
            ['title' => $title, 'description' => $description, 'options' => $pipelineOptions],
        );

        return (int) $this->connection->lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM dev_tasks WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecent(int $limit = 20, ?string $statusFilter = null): array
    {
        $sql = 'SELECT * FROM dev_tasks';
        $params = [];

        if (null !== $statusFilter) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $statusFilter;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $params['limit'] = $limit;

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function updateStatus(int $id, string $status, array $extra = []): void
    {
        $sets = ['status = :status'];
        $params = ['id' => $id, 'status' => $status];

        foreach ($extra as $key => $value) {
            if ('now()' === $value) {
                $sets[] = "{$key} = now()";
            } else {
                $sets[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }

        $sql = 'UPDATE dev_tasks SET '.implode(', ', $sets).' WHERE id = :id';
        $this->connection->executeStatement($sql, $params);
    }

    /**
     * @param list<array{role: string, content: string}> $chatHistory
     */
    public function updateRefinedSpec(int $id, string $spec, array $chatHistory): void
    {
        $this->connection->executeStatement(
            'UPDATE dev_tasks SET refined_spec = :spec, chat_history = :chat WHERE id = :id',
            [
                'id' => $id,
                'spec' => $spec,
                'chat' => json_encode($chatHistory, \JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function updatePr(int $id, string $prUrl, ?int $prNumber = null): void
    {
        $this->connection->executeStatement(
            'UPDATE dev_tasks SET pr_url = :url, pr_number = :num WHERE id = :id',
            ['id' => $id, 'url' => $prUrl, 'num' => $prNumber],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findNextPending(): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM dev_tasks WHERE status = :status ORDER BY created_at ASC LIMIT 1',
            ['status' => 'pending'],
        );

        return false === $row ? null : $row;
    }

    /**
     * @return array<string, int>
     */
    public function getStats(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT status, COUNT(*) as cnt FROM dev_tasks WHERE created_at > now() - interval '7 days' GROUP BY status",
        );

        $stats = ['total' => 0, 'draft' => 0, 'pending' => 0, 'running' => 0, 'success' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $cnt = (int) $row['cnt'];
            $stats[(string) $row['status']] = $cnt;
            $stats['total'] += $cnt;
        }

        return $stats;
    }
}
