<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\A2AGateway\A2AClientInterface;
use Psr\Log\LoggerInterface;

final class SchedulerService
{
    public function __construct(
        private readonly ScheduledJobRepository $repository,
        private readonly CronExpressionHelper $cronHelper,
        private readonly A2AClientInterface $a2aClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute one scheduler tick: find due jobs, invoke each, handle retry/dead-letter.
     *
     * @return int number of jobs executed
     */
    public function tick(): int
    {
        $jobs = $this->repository->findDueJobs();
        $executed = 0;

        foreach ($jobs as $job) {
            $this->executeJob($job);
            ++$executed;
        }

        return $executed;
    }

    /**
     * Register scheduled jobs from an agent manifest.
     *
     * @param array<string, mixed> $manifest
     *
     * @return int number of jobs registered
     */
    public function registerFromManifest(string $agentName, array $manifest): int
    {
        $scheduledJobs = $manifest['scheduled_jobs'] ?? null;

        if (!is_array($scheduledJobs) || [] === $scheduledJobs) {
            return 0;
        }

        $count = 0;

        foreach ($scheduledJobs as $jobDef) {
            if (!is_array($jobDef)) {
                continue;
            }

            $jobName = (string) ($jobDef['job_name'] ?? '');
            $skillId = (string) ($jobDef['skill_id'] ?? '');

            if ('' === $jobName || '' === $skillId) {
                $this->logger->warning('Skipping invalid scheduled_job definition', [
                    'agent' => $agentName,
                    'job_def' => $jobDef,
                ]);
                continue;
            }

            $cronExpression = isset($jobDef['cron']) && is_string($jobDef['cron']) ? $jobDef['cron'] : null;
            $payload = is_array($jobDef['payload'] ?? null) ? $jobDef['payload'] : [];
            $maxRetries = isset($jobDef['max_retries']) && is_int($jobDef['max_retries']) ? $jobDef['max_retries'] : 3;
            $retryDelaySeconds = isset($jobDef['retry_delay_seconds']) && is_int($jobDef['retry_delay_seconds']) ? $jobDef['retry_delay_seconds'] : 60;
            $timezone = isset($jobDef['timezone']) && is_string($jobDef['timezone']) ? $jobDef['timezone'] : 'UTC';

            $nextRunAt = $this->computeInitialNextRun($cronExpression, $timezone);

            $this->repository->registerJob(
                $agentName,
                $jobName,
                $skillId,
                $payload,
                $cronExpression,
                $nextRunAt,
                $maxRetries,
                $retryDelaySeconds,
                $timezone,
            );

            $this->logger->info('Scheduled job registered', [
                'agent' => $agentName,
                'job' => $jobName,
                'skill' => $skillId,
                'cron' => $cronExpression,
                'next_run_at' => $nextRunAt,
            ]);

            ++$count;
        }

        return $count;
    }

    /**
     * Remove all scheduled jobs for an agent.
     */
    public function removeByAgent(string $agentName): int
    {
        $count = $this->repository->deleteByAgent($agentName);

        if ($count > 0) {
            $this->logger->info('Scheduled jobs removed', ['agent' => $agentName, 'count' => $count]);
        }

        return $count;
    }

    /**
     * Enable all scheduled jobs for an agent.
     */
    public function enableByAgent(string $agentName): int
    {
        return $this->repository->enableByAgent($agentName);
    }

    /**
     * Disable all scheduled jobs for an agent.
     */
    public function disableByAgent(string $agentName): int
    {
        return $this->repository->disableByAgent($agentName);
    }

    /**
     * @param array<string, mixed> $job
     */
    private function executeJob(array $job): void
    {
        $id = (string) $job['id'];
        $skillId = (string) $job['skill_id'];
        $agentName = (string) $job['agent_name'];
        $jobName = (string) $job['job_name'];

        $payload = [];
        if (is_string($job['payload'] ?? null) && '' !== $job['payload']) {
            try {
                $decoded = json_decode((string) $job['payload'], true, 512, JSON_THROW_ON_ERROR);
                $payload = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                $payload = [];
            }
        } elseif (is_array($job['payload'] ?? null)) {
            $payload = (array) $job['payload'];
        }

        $traceId = bin2hex(random_bytes(16));
        $requestId = 'sched_'.bin2hex(random_bytes(8));

        $this->logger->info('Scheduler executing job', [
            'job_id' => $id,
            'agent' => $agentName,
            'job' => $jobName,
            'skill' => $skillId,
            'trace_id' => $traceId,
        ]);

        try {
            $result = $this->a2aClient->invoke($skillId, $payload, $traceId, $requestId, 'scheduler');
            $status = (string) ($result['status'] ?? 'unknown');

            if ('failed' === $status) {
                $this->handleFailure($job, $status);

                return;
            }

            $nextRunAt = $this->computeNextRunAfterSuccess($job);

            $this->repository->updateAfterRun($id, 'completed', $nextRunAt);

            $this->logger->info('Scheduler job completed', [
                'job_id' => $id,
                'agent' => $agentName,
                'job' => $jobName,
                'next_run_at' => $nextRunAt,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Scheduler job threw exception', [
                'job_id' => $id,
                'agent' => $agentName,
                'job' => $jobName,
                'error' => $e->getMessage(),
            ]);

            $this->handleFailure($job, 'failed');
        }
    }

    /**
     * @param array<string, mixed> $job
     */
    private function handleFailure(array $job, string $status): void
    {
        $id = (string) $job['id'];
        $retryCount = (int) ($job['retry_count'] ?? 0) + 1;
        $maxRetries = (int) ($job['max_retries'] ?? 3);
        $retryDelaySeconds = (int) ($job['retry_delay_seconds'] ?? 60);

        if ($retryCount >= $maxRetries) {
            $this->repository->disableJob($id);
            $this->logger->warning('Scheduler job dead-lettered (max retries exceeded)', [
                'job_id' => $id,
                'agent' => (string) ($job['agent_name'] ?? ''),
                'job' => (string) ($job['job_name'] ?? ''),
                'retry_count' => $retryCount,
                'max_retries' => $maxRetries,
            ]);

            return;
        }

        $nextRetryAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('+%d seconds', $retryDelaySeconds))
            ->format('Y-m-d H:i:sP');

        $this->repository->updateRetry($id, $retryCount, $nextRetryAt);

        $this->logger->warning('Scheduler job failed, will retry', [
            'job_id' => $id,
            'agent' => (string) ($job['agent_name'] ?? ''),
            'job' => (string) ($job['job_name'] ?? ''),
            'retry_count' => $retryCount,
            'next_retry_at' => $nextRetryAt,
        ]);
    }

    /**
     * @param array<string, mixed> $job
     */
    private function computeNextRunAfterSuccess(array $job): ?string
    {
        $cronExpression = isset($job['cron_expression']) && is_string($job['cron_expression'])
            ? $job['cron_expression']
            : null;

        if (null === $cronExpression) {
            // One-shot job — no next run
            return null;
        }

        $timezone = isset($job['timezone']) && is_string($job['timezone']) ? $job['timezone'] : 'UTC';

        return $this->cronHelper->computeNextRun($cronExpression, $timezone)
            ->format('Y-m-d H:i:sP');
    }

    private function computeInitialNextRun(?string $cronExpression, string $timezone): string
    {
        if (null === $cronExpression) {
            // One-shot: run immediately
            return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:sP');
        }

        return $this->cronHelper->computeNextRun($cronExpression, $timezone)->format('Y-m-d H:i:sP');
    }
}
