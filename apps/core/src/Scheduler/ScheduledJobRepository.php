<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Tenant\TenantContext;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final class ScheduledJobRepository implements ScheduledJobRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * Due jobs are fetched globally — the scheduler process handles all tenants.
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
            ORDER BY next_run_at
            FOR UPDATE SKIP LOCKED
            SQL,
        );
    }

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
    ): void {
        $tenantId = $this->tenantContext->requireTenantId();

        $this->connection->executeStatement(
            <<<'SQL'
            INSERT INTO scheduled_jobs
                (agent_name, job_name, skill_id, payload, cron_expression, next_run_at, max_retries, retry_delay_seconds, timezone, source, tenant_id)
            VALUES
                (:agentName, :jobName, :skillId, :payload, :cronExpression, :nextRunAt, :maxRetries, :retryDelaySeconds, :timezone, :source, :tenantId)
            ON CONFLICT (agent_name, job_name, tenant_id) DO UPDATE SET
                skill_id             = EXCLUDED.skill_id,
                payload              = EXCLUDED.payload,
                cron_expression      = EXCLUDED.cron_expression,
                next_run_at          = EXCLUDED.next_run_at,
                max_retries          = EXCLUDED.max_retries,
                retry_delay_seconds  = EXCLUDED.retry_delay_seconds,
                timezone             = EXCLUDED.timezone,
                enabled              = TRUE,
                updated_at           = now()
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
                'source' => $source,
                'tenantId' => $tenantId,
            ],
        );
    }

    public function deleteByAgent(string $agentName): int
    {
        if ($this->tenantContext->isSet()) {
            return (int) $this->connection->executeStatement(
                'DELETE FROM scheduled_jobs WHERE agent_name = :agentName AND tenant_id = :tenantId',
                ['agentName' => $agentName, 'tenantId' => $this->tenantContext->requireTenantId()],
            );
        }

        return (int) $this->connection->executeStatement(
            'DELETE FROM scheduled_jobs WHERE agent_name = :agentName',
            ['agentName' => $agentName],
        );
    }

    public function enableByAgent(string $agentName): int
    {
        if ($this->tenantContext->isSet()) {
            return (int) $this->connection->executeStatement(
                'UPDATE scheduled_jobs SET enabled = TRUE, updated_at = now() WHERE agent_name = :agentName AND tenant_id = :tenantId',
                ['agentName' => $agentName, 'tenantId' => $this->tenantContext->requireTenantId()],
            );
        }

        return (int) $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET enabled = TRUE, updated_at = now() WHERE agent_name = :agentName',
            ['agentName' => $agentName],
        );
    }

    public function disableByAgent(string $agentName): int
    {
        if ($this->tenantContext->isSet()) {
            return (int) $this->connection->executeStatement(
                'UPDATE scheduled_jobs SET enabled = FALSE, updated_at = now() WHERE agent_name = :agentName AND tenant_id = :tenantId',
                ['agentName' => $agentName, 'tenantId' => $this->tenantContext->requireTenantId()],
            );
        }

        return (int) $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET enabled = FALSE, updated_at = now() WHERE agent_name = :agentName',
            ['agentName' => $agentName],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        if ($this->tenantContext->isSet()) {
            return $this->connection->fetchAllAssociative(
                'SELECT * FROM scheduled_jobs WHERE tenant_id = :tenantId ORDER BY agent_name, job_name',
                ['tenantId' => $this->tenantContext->requireTenantId()],
            );
        }

        return $this->connection->fetchAllAssociative(
            'SELECT * FROM scheduled_jobs ORDER BY agent_name, job_name',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findByAgent(string $agentName): array
    {
        /* @var list<array<string, mixed>> */
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM scheduled_jobs WHERE agent_name = :agentName ORDER BY job_name',
            ['agentName' => $agentName],
        );
    }

    /**
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

    public function updateAfterRun(string $id, string $status, ?string $nextRunAt): void
    {
        if (null !== $nextRunAt) {
            $this->connection->executeStatement(
                <<<'SQL'
                UPDATE scheduled_jobs
                SET last_run_at = now(),
                    last_status = :status,
                    retry_count = 0,
                    next_run_at = :nextRunAt::TIMESTAMPTZ,
                    updated_at  = now()
                WHERE id = :id
                SQL,
                ['id' => $id, 'status' => $status, 'nextRunAt' => $nextRunAt],
            );
        } else {
            $this->connection->executeStatement(
                <<<'SQL'
                UPDATE scheduled_jobs
                SET last_run_at = now(),
                    last_status = :status,
                    retry_count = 0,
                    enabled     = FALSE,
                    updated_at  = now()
                WHERE id = :id
                SQL,
                ['id' => $id, 'status' => $status],
            );
        }
    }

    public function updateRetry(string $id, int $retryCount, string $nextRunAt): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
            UPDATE scheduled_jobs
            SET retry_count = :retryCount,
                next_run_at = :nextRunAt::TIMESTAMPTZ,
                last_status = 'failed',
                updated_at  = now()
            WHERE id = :id
            SQL,
            ['id' => $id, 'retryCount' => $retryCount, 'nextRunAt' => $nextRunAt],
        );
    }

    public function disableJob(string $id): void
    {
        $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET enabled = FALSE, last_status = \'dead_letter\', updated_at = now() WHERE id = :id',
            ['id' => $id],
        );
    }

    public function triggerNow(string $id): void
    {
        $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET next_run_at = now(), updated_at = now() WHERE id = :id',
            ['id' => $id],
        );
    }

    public function deleteJob(string $id): void
    {
        $this->connection->executeStatement(
            'DELETE FROM scheduled_jobs WHERE id = :id',
            ['id' => $id],
        );
    }

    public function toggleEnabled(string $id, bool $enabled): void
    {
        $this->connection->executeStatement(
            'UPDATE scheduled_jobs SET enabled = :enabled, updated_at = now() WHERE id = :id',
            ['id' => $id, 'enabled' => $enabled],
            ['enabled' => Types::BOOLEAN],
        );
    }
}
