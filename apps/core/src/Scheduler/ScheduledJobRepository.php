<?php

declare(strict_types=1);

namespace App\Scheduler;

use Doctrine\DBAL\Connection;

class ScheduledJobRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Find jobs that are due to run, locking them to prevent concurrent execution.
     *
     * @return list<array<string, mixed>>
     */
    public function findDueJobs(): array
    {
        /* @var list<array<string, mixed>> */
        return $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT * FROM scheduled_jobs
            WHERE enabled = TRUE AND next_run_at <= now()
            ORDER BY next_run_at ASC
            FOR UPDATE SKIP LOCKED
            SQL,
        );
    }

    /**
     * Register or update a scheduled job (upsert by agent_name + job_name).
     *
     * @param array<string, mixed> $payload
     */
    public function registerJob(
        string $agentName,
        string $jobName,
        string $skillId,
        array $payload,
        ?string $cronExpression,
        string $nextRunAt,
        int $maxRetries,
        int $retryDelaySeconds,
        string $timezone,
    ): void {
        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO scheduled_jobs
                (agent_name, job_name, skill_id, payload, cron_expression, next_run_at, max_retries, retry_delay_seconds, timezone)
            VALUES
                (:agentName, :jobName, :skillId, :payload, :cronExpression, :nextRunAt, :maxRetries, :retryDelaySeconds, :timezone)
            ON CONFLICT (agent_name, job_name) DO UPDATE SET
                skill_id = EXCLUDED.skill_id,
                payload = EXCLUDED.payload,
                cron_expression = EXCLUDED.cron_expression,
                next_run_at = EXCLUDED.next_run_at,
                max_retries = EXCLUDED.max_retries,
                retry_delay_seconds = EXCLUDED.retry_delay_seconds,
                timezone = EXCLUDED.timezone,
                enabled = TRUE,
                retry_count = 0,
                updated_at = now()
            SQL,
            [
                'agentName' => $agentName,
                'jobName' => $jobName,
                'skillId' => $skillId,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'cronExpression' => $cronExpression,
                'nextRunAt' => $nextRunAt,
                'maxRetries' => $maxRetries,
                'retryDelaySeconds' => $retryDelaySeconds,
                'timezone' => $timezone,
            ],
        );
    }

    /**
     * Delete all jobs for a given agent.
     */
    public function deleteByAgent(string $agentName): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM scheduled_jobs WHERE agent_name = :agentName',
            ['agentName' => $agentName],
        );
    }

    /**
     * Enable all jobs for a given agent.
     */
    public function enableByAgent(string $agentName): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET enabled = TRUE, updated_at = now() WHERE agent_name = :agentName',
            ['agentName' => $agentName],
        );
    }

    /**
     * Disable all jobs for a given agent.
     */
    public function disableByAgent(string $agentName): int
    {
        return (int) $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET enabled = FALSE, updated_at = now() WHERE agent_name = :agentName',
            ['agentName' => $agentName],
        );
    }

    /**
     * Find all jobs for admin listing.
     *
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        /* @var list<array<string, mixed>> */
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM scheduled_jobs ORDER BY agent_name, job_name',
        );
    }

    /**
     * Find a single job by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scheduled_jobs WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : $row;
    }

    /**
     * Update job state after a successful or failed run.
     */
    public function updateAfterRun(string $id, string $status, ?string $nextRunAt): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE scheduled_jobs
            SET last_run_at = now(),
                last_status = :status,
                retry_count = 0,
                next_run_at = COALESCE(:nextRunAt::TIMESTAMPTZ, next_run_at),
                enabled = CASE WHEN :nextRunAt IS NULL THEN FALSE ELSE enabled END,
                updated_at = now()
            WHERE id = :id
            SQL,
            [
                'id' => $id,
                'status' => $status,
                'nextRunAt' => $nextRunAt,
            ],
        );
    }

    /**
     * Update retry state after a failed run.
     */
    public function updateRetry(string $id, int $retryCount, string $nextRunAt): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE scheduled_jobs
            SET last_run_at = now(),
                last_status = 'failed',
                retry_count = :retryCount,
                next_run_at = :nextRunAt::TIMESTAMPTZ,
                updated_at = now()
            WHERE id = :id
            SQL,
            [
                'id' => $id,
                'retryCount' => $retryCount,
                'nextRunAt' => $nextRunAt,
            ],
        );
    }

    /**
     * Disable a job (dead letter — max retries exceeded).
     */
    public function disableJob(string $id): void
    {
        $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET enabled = FALSE, last_status = \'failed\', updated_at = now() WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Trigger a job to run immediately (set next_run_at = now()).
     */
    public function triggerNow(string $id): void
    {
        $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET next_run_at = now(), updated_at = now() WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * Toggle enabled/disabled state for a job.
     */
    public function toggleEnabled(string $id, bool $enabled): void
    {
        $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET enabled = :enabled, updated_at = now() WHERE id = :id',
            ['id' => $id, 'enabled' => $enabled],
        );
    }
}
