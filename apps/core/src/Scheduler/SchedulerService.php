<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\A2AGateway\A2AClientInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

final class SchedulerService
{
    public function __construct(
        private readonly ScheduledJobRepositoryInterface $repository,
        private readonly CronExpressionHelperInterface $cronHelper,
        private readonly A2AClientInterface $a2aClient,
        private readonly LoggerInterface $logger,
        private readonly Connection $connection,
    ) {
    }

    public function tick(): int
    {
        $this->connection->beginTransaction();

        try {
            $jobs = $this->repository->findDueJobs();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        $executed = 0;

        foreach ($jobs as $job) {
            $id = (string) $job['id'];
            $skillId = (string) $job['skill_id'];
            $payload = is_string($job['payload'])
                ? (array) json_decode($job['payload'], true)
                : (array) ($job['payload'] ?? []);
            $cronExpression = isset($job['cron_expression']) ? (string) $job['cron_expression'] : null;
            $timezone = (string) ($job['timezone'] ?? 'UTC');
            $retryCount = (int) ($job['retry_count'] ?? 0);
            $maxRetries = (int) ($job['max_retries'] ?? 3);
            $retryDelaySeconds = (int) ($job['retry_delay_seconds'] ?? 60);

            $traceId = bin2hex(random_bytes(16));
            $requestId = bin2hex(random_bytes(8));

            try {
                $result = $this->a2aClient->invoke($skillId, $payload, $traceId, $requestId, 'scheduler');
                $status = (string) ($result['status'] ?? 'unknown');

                if ('failed' === $status) {
                    $this->handleFailure($id, $retryCount, $maxRetries, $retryDelaySeconds, $job);
                } else {
                    $nextRunAt = null !== $cronExpression
                        ? $this->cronHelper->computeNextRun($cronExpression, $timezone)->format('Y-m-d H:i:sP')
                        : null;

                    $this->repository->updateAfterRun($id, 'completed', $nextRunAt);

                    $this->logger->info('Scheduled job executed', [
                        'job_id' => $id,
                        'agent' => $job['agent_name'],
                        'job' => $job['job_name'],
                        'skill' => $skillId,
                        'next_run_at' => $nextRunAt,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->handleFailure($id, $retryCount, $maxRetries, $retryDelaySeconds, $job, $e);
            }

            ++$executed;
        }

        $this->connection->commit();

        return $executed;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function registerFromManifest(string $agentName, array $manifest): int
    {
        $scheduledJobs = $manifest['scheduled_jobs'] ?? [];
        if (!is_array($scheduledJobs) || [] === $scheduledJobs) {
            return 0;
        }

        $registered = 0;

        foreach ($scheduledJobs as $job) {
            if (!is_array($job)) {
                continue;
            }

            $jobName = (string) ($job['name'] ?? '');
            $skillId = (string) ($job['skill_id'] ?? '');
            $cronExpression = isset($job['cron_expression']) ? (string) $job['cron_expression'] : null;
            $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
            $maxRetries = (int) ($job['max_retries'] ?? 3);
            $retryDelaySeconds = (int) ($job['retry_delay_seconds'] ?? 60);
            $timezone = (string) ($job['timezone'] ?? 'UTC');

            if ('' === $jobName || '' === $skillId) {
                continue;
            }

            $nextRunAt = null !== $cronExpression
                ? $this->cronHelper->computeNextRun($cronExpression, $timezone)->format('Y-m-d H:i:sP')
                : (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

            $this->repository->registerJob(
                $agentName,
                $jobName,
                $skillId,
                $payload,
                $cronExpression,
                $maxRetries,
                $retryDelaySeconds,
                $timezone,
                $nextRunAt,
            );

            ++$registered;
        }

        return $registered;
    }

    public function removeByAgent(string $agentName): int
    {
        return $this->repository->deleteByAgent($agentName);
    }

    public function enableByAgent(string $agentName): int
    {
        $count = $this->repository->enableByAgent($agentName);

        if ($count > 0) {
            // Recompute next_run_at for all jobs of this agent
            $jobs = $this->repository->findByAgent($agentName);
            foreach ($jobs as $job) {
                $cronExpression = isset($job['cron_expression']) ? (string) $job['cron_expression'] : null;
                if (null === $cronExpression) {
                    continue;
                }
                $timezone = (string) ($job['timezone'] ?? 'UTC');
                $nextRunAt = $this->cronHelper->computeNextRun($cronExpression, $timezone)->format('Y-m-d H:i:sP');
                $this->repository->updateAfterRun((string) $job['id'], (string) ($job['last_status'] ?? 'pending'), $nextRunAt);
            }
        }

        return $count;
    }

    public function disableByAgent(string $agentName): int
    {
        return $this->repository->disableByAgent($agentName);
    }

    /**
     * @param array<string, mixed> $job
     */
    private function handleFailure(
        string $id,
        int $retryCount,
        int $maxRetries,
        int $retryDelaySeconds,
        array $job,
        ?\Throwable $exception = null,
    ): void {
        $newRetryCount = $retryCount + 1;

        if ($newRetryCount >= $maxRetries) {
            $this->repository->disableJob($id);
            $this->logger->warning('Scheduled job dead-lettered after max retries', [
                'job_id' => $id,
                'agent' => $job['agent_name'],
                'job' => $job['job_name'],
                'retry_count' => $newRetryCount,
                'max_retries' => $maxRetries,
                'exception' => $exception?->getMessage(),
            ]);
        } else {
            $nextRetryAt = (new \DateTimeImmutable('now'))
                ->modify(sprintf('+%d seconds', $retryDelaySeconds))
                ->format('Y-m-d H:i:sP');

            $this->repository->updateRetry($id, $newRetryCount, $nextRetryAt);
            $this->logger->info('Scheduled job failed, will retry', [
                'job_id' => $id,
                'agent' => $job['agent_name'],
                'job' => $job['job_name'],
                'retry_count' => $newRetryCount,
                'next_retry_at' => $nextRetryAt,
                'exception' => $exception?->getMessage(),
            ]);
        }
    }
}
