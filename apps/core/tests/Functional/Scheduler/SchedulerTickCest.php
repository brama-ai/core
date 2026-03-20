<?php

declare(strict_types=1);

namespace App\Tests\Functional\Scheduler;

use App\Scheduler\ScheduledJobRepository;
use App\Scheduler\SchedulerJobLogRepository;
use App\Scheduler\SchedulerService;

/**
 * E2E functional tests for the scheduler tick lifecycle.
 *
 * These tests exercise the full path: create a due job in the DB,
 * run tick() through the real service graph, and verify the resulting
 * job state and log entries in the database.
 *
 * The AsyncA2ADispatcher will attempt real HTTP calls against non-existent
 * agents, so dispatched jobs are expected to fail with connection errors —
 * this is intentional and tests the error-handling path end-to-end.
 */
final class SchedulerTickCest
{
    public function tickPicksDueJobAndCreatesLog(\FunctionalTester $I): void
    {
        /** @var ScheduledJobRepository $repo */
        $repo = $I->grabService(ScheduledJobRepository::class);
        /** @var SchedulerService $scheduler */
        $scheduler = $I->grabService(SchedulerService::class);
        /** @var SchedulerJobLogRepository $logRepo */
        $logRepo = $I->grabService(SchedulerJobLogRepository::class);

        $agentName = 'e2e-tick-agent-'.bin2hex(random_bytes(4));
        $pastRunAt = (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:sP');

        $repo->registerJob($agentName, 'e2e-job', 'e2e.ping', ['msg' => 'hello'], null, 1, 60, 'UTC', $pastRunAt);

        $jobs = $repo->findByAgent($agentName);
        $I->assertCount(1, $jobs);
        $jobId = (string) $jobs[0]['id'];
        $I->assertTrue((bool) $jobs[0]['enabled']);

        // Run tick — job will be picked up and dispatched (will fail: no agent running)
        $executed = $scheduler->tick();

        $I->assertGreaterThanOrEqual(1, $executed);

        // Job should be disabled (one-shot, no cron)
        $updated = $repo->findById($jobId);
        $I->assertNotNull($updated);
        $I->assertFalse((bool) $updated['enabled']);

        // A log entry should exist
        $logs = $logRepo->findByJob($jobId);
        $I->assertGreaterThanOrEqual(1, count($logs));
        $log = $logs[0];
        $I->assertSame('e2e.ping', $log['skill_id']);
        $I->assertNotNull($log['started_at']);
        $I->assertNotNull($log['finished_at']);
    }

    public function tickSkipsDisabledJobs(\FunctionalTester $I): void
    {
        /** @var ScheduledJobRepository $repo */
        $repo = $I->grabService(ScheduledJobRepository::class);
        /** @var SchedulerService $scheduler */
        $scheduler = $I->grabService(SchedulerService::class);

        $agentName = 'e2e-disabled-agent-'.bin2hex(random_bytes(4));
        $pastRunAt = (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:sP');

        $repo->registerJob($agentName, 'disabled-job', 'skip.me', [], null, 1, 60, 'UTC', $pastRunAt);

        // Disable the job
        $jobs = $repo->findByAgent($agentName);
        $repo->toggleEnabled((string) $jobs[0]['id'], false);

        $executed = $scheduler->tick();

        // The disabled job should not have been picked
        $updated = $repo->findById((string) $jobs[0]['id']);
        $I->assertSame(null, $updated['last_run_at']);
    }

    public function tickSkipsFutureJobs(\FunctionalTester $I): void
    {
        /** @var ScheduledJobRepository $repo */
        $repo = $I->grabService(ScheduledJobRepository::class);
        /** @var SchedulerService $scheduler */
        $scheduler = $I->grabService(SchedulerService::class);

        $agentName = 'e2e-future-agent-'.bin2hex(random_bytes(4));
        $futureRunAt = (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:sP');

        $repo->registerJob($agentName, 'future-job', 'later.skill', [], '0 * * * *', 3, 60, 'UTC', $futureRunAt);

        $executed = $scheduler->tick();

        // Future job should not be picked
        $job = $repo->findByAgent($agentName)[0];
        $I->assertSame(null, $job['last_run_at']);
    }

    public function tickRetriesFailedJobAndEventuallyDeadLetters(\FunctionalTester $I): void
    {
        /** @var ScheduledJobRepository $repo */
        $repo = $I->grabService(ScheduledJobRepository::class);
        /** @var SchedulerService $scheduler */
        $scheduler = $I->grabService(SchedulerService::class);
        /** @var SchedulerJobLogRepository $logRepo */
        $logRepo = $I->grabService(SchedulerJobLogRepository::class);

        $agentName = 'e2e-retry-agent-'.bin2hex(random_bytes(4));
        $pastRunAt = (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:sP');

        // max_retries=2, retry_delay=0 so retry is immediate
        $repo->registerJob($agentName, 'retry-job', 'fail.skill', [], '* * * * *', 2, 0, 'UTC', $pastRunAt);

        $jobs = $repo->findByAgent($agentName);
        $jobId = (string) $jobs[0]['id'];

        // Tick 1: first attempt fails, retry_count goes to 1
        $scheduler->tick();

        $job = $repo->findById($jobId);
        $I->assertSame(1, (int) $job['retry_count']);
        $I->assertSame('failed', $job['last_status']);
        $I->assertTrue((bool) $job['enabled'], 'Job should still be enabled for retry');

        // Tick 2: second attempt fails, retry_count goes to 2 → dead-lettered
        $scheduler->tick();

        $job = $repo->findById($jobId);
        $I->assertFalse((bool) $job['enabled'], 'Job should be disabled after max retries');
        $I->assertSame('dead_letter', $job['last_status']);

        // Should have 2 log entries (one per attempt)
        $logs = $logRepo->findByJob($jobId);
        $I->assertCount(2, $logs);
        $I->assertSame('failed', $logs[0]['status']);
        $I->assertSame('failed', $logs[1]['status']);
    }

    public function tickDispatchesMultipleDueJobsInOneBatch(\FunctionalTester $I): void
    {
        /** @var ScheduledJobRepository $repo */
        $repo = $I->grabService(ScheduledJobRepository::class);
        /** @var SchedulerService $scheduler */
        $scheduler = $I->grabService(SchedulerService::class);
        /** @var SchedulerJobLogRepository $logRepo */
        $logRepo = $I->grabService(SchedulerJobLogRepository::class);

        $agentName = 'e2e-batch-agent-'.bin2hex(random_bytes(4));
        $pastRunAt = (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:sP');

        $repo->registerJob($agentName, 'batch-job-1', 'batch.a', ['i' => 1], null, 1, 60, 'UTC', $pastRunAt);
        $repo->registerJob($agentName, 'batch-job-2', 'batch.b', ['i' => 2], null, 1, 60, 'UTC', $pastRunAt);
        $repo->registerJob($agentName, 'batch-job-3', 'batch.c', ['i' => 3], null, 1, 60, 'UTC', $pastRunAt);

        $executed = $scheduler->tick();

        $I->assertGreaterThanOrEqual(3, $executed);

        // All 3 should have log entries
        $jobs = $repo->findByAgent($agentName);
        foreach ($jobs as $job) {
            $logs = $logRepo->findByJob((string) $job['id']);
            $I->assertGreaterThanOrEqual(1, count($logs), "Job {$job['job_name']} should have a log entry");
        }
    }

    public function tickRecordsCronJobNextRunAtAfterExecution(\FunctionalTester $I): void
    {
        /** @var ScheduledJobRepository $repo */
        $repo = $I->grabService(ScheduledJobRepository::class);
        /** @var SchedulerService $scheduler */
        $scheduler = $I->grabService(SchedulerService::class);

        $agentName = 'e2e-cron-agent-'.bin2hex(random_bytes(4));
        $pastRunAt = (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:sP');

        // Cron job that runs every hour — high max_retries so failure doesn't dead-letter
        $repo->registerJob($agentName, 'hourly-job', 'hourly.skill', [], '0 * * * *', 10, 60, 'UTC', $pastRunAt);

        $jobs = $repo->findByAgent($agentName);
        $jobId = (string) $jobs[0]['id'];

        $scheduler->tick();

        $updated = $repo->findById($jobId);
        $I->assertNotNull($updated);

        // Job should still be enabled (dispatch fails but retries remain)
        $I->assertTrue((bool) $updated['enabled']);

        // next_run_at was advanced by cron in Phase 1 (before dispatch),
        // but failure triggers updateRetry with retry_delay offset.
        // Either way, next_run_at should be set and in the future.
        $I->assertNotNull($updated['next_run_at']);
        $nextRun = new \DateTimeImmutable($updated['next_run_at']);
        $I->assertGreaterThan(new \DateTimeImmutable('-1 minute'), $nextRun);
    }
}
