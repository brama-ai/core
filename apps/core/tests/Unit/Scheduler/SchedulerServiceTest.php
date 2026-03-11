<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\A2AGateway\A2AClientInterface;
use App\Scheduler\CronExpressionHelperInterface;
use App\Scheduler\ScheduledJobRepositoryInterface;
use App\Scheduler\SchedulerService;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SchedulerServiceTest extends Unit
{
    private ScheduledJobRepositoryInterface&MockObject $repository;
    private CronExpressionHelperInterface&MockObject $cronHelper;
    private A2AClientInterface&MockObject $a2aClient;
    private LoggerInterface&MockObject $logger;
    private Connection&MockObject $connection;
    private SchedulerService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ScheduledJobRepositoryInterface::class);
        $this->cronHelper = $this->createMock(CronExpressionHelperInterface::class);
        $this->a2aClient = $this->createMock(A2AClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->connection = $this->createMock(Connection::class);

        $this->service = new SchedulerService(
            $this->repository,
            $this->cronHelper,
            $this->a2aClient,
            $this->logger,
            $this->connection,
        );
    }

    public function testTickReturnsZeroWhenNoDueJobs(): void
    {
        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([]);

        $this->a2aClient->expects($this->never())
            ->method('invoke');

        $this->assertSame(0, $this->service->tick());
    }

    public function testTickExecutesJobAndUpdatesAfterSuccess(): void
    {
        $job = $this->makeJob('job-1', '* * * * *');

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'completed']);

        $nextRun = new \DateTimeImmutable('+1 minute');
        $this->cronHelper->expects($this->once())
            ->method('computeNextRun')
            ->willReturn($nextRun);

        $this->repository->expects($this->once())
            ->method('updateAfterRun')
            ->with($job['id'], 'completed', $this->isString());

        $this->assertSame(1, $this->service->tick());
    }

    public function testTickDisablesOneShotJobAfterSuccess(): void
    {
        $job = $this->makeJob('job-2', null);

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'completed']);

        $this->cronHelper->expects($this->never())
            ->method('computeNextRun');

        $this->repository->expects($this->once())
            ->method('updateAfterRun')
            ->with($job['id'], 'completed', null);

        $this->assertSame(1, $this->service->tick());
    }

    public function testTickIncrementsRetryCountOnFailure(): void
    {
        $job = $this->makeJob('job-3', '* * * * *', retryCount: 0, maxRetries: 3);

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'failed']);

        $this->repository->expects($this->once())
            ->method('updateRetry')
            ->with($job['id'], 1, $this->isString());

        $this->repository->expects($this->never())
            ->method('disableJob');

        $this->assertSame(1, $this->service->tick());
    }

    public function testTickDeadLettersJobWhenMaxRetriesReached(): void
    {
        $job = $this->makeJob('job-4', '* * * * *', retryCount: 2, maxRetries: 3);

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'failed']);

        $this->repository->expects($this->once())
            ->method('disableJob')
            ->with($job['id']);

        $this->repository->expects($this->never())
            ->method('updateRetry');

        $this->assertSame(1, $this->service->tick());
    }

    public function testTickResetsRetryCountOnSuccessAfterFailures(): void
    {
        $job = $this->makeJob('job-5', '* * * * *', retryCount: 2);

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'completed']);

        $nextRun = new \DateTimeImmutable('+1 minute');
        $this->cronHelper->expects($this->once())
            ->method('computeNextRun')
            ->willReturn($nextRun);

        // updateAfterRun resets retry_count to 0 in the SQL
        $this->repository->expects($this->once())
            ->method('updateAfterRun')
            ->with($job['id'], 'completed', $this->isString());

        $this->assertSame(1, $this->service->tick());
    }

    public function testTickHandlesExceptionAsFailure(): void
    {
        $job = $this->makeJob('job-6', '* * * * *', retryCount: 0, maxRetries: 3);

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->repository->expects($this->once())
            ->method('updateRetry')
            ->with($job['id'], 1, $this->isString());

        $this->assertSame(1, $this->service->tick());
    }

    public function testRegisterFromManifestSkipsEmptyScheduledJobs(): void
    {
        $this->repository->expects($this->never())
            ->method('registerJob');

        $count = $this->service->registerFromManifest('test-agent', []);
        $this->assertSame(0, $count);
    }

    public function testRegisterFromManifestRegistersJobs(): void
    {
        $manifest = [
            'scheduled_jobs' => [
                [
                    'name' => 'daily-sync',
                    'skill_id' => 'sync.run',
                    'cron_expression' => '0 0 * * *',
                    'payload' => ['mode' => 'full'],
                    'max_retries' => 3,
                    'retry_delay_seconds' => 60,
                    'timezone' => 'UTC',
                ],
            ],
        ];

        $nextRun = new \DateTimeImmutable('+1 day');
        $this->cronHelper->expects($this->once())
            ->method('computeNextRun')
            ->with('0 0 * * *', 'UTC')
            ->willReturn($nextRun);

        $this->repository->expects($this->once())
            ->method('registerJob')
            ->with('test-agent', 'daily-sync', 'sync.run', ['mode' => 'full'], '0 0 * * *', 3, 60, 'UTC', $this->isString());

        $count = $this->service->registerFromManifest('test-agent', $manifest);
        $this->assertSame(1, $count);
    }

    public function testCatchUpPolicyRunsOverdueJobOnce(): void
    {
        // A job with next_run_at in the past should be picked up by findDueJobs
        // and executed once, then next_run_at is recomputed
        $job = $this->makeJob('job-catchup', '0 * * * *');

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'completed']);

        $nextRun = new \DateTimeImmutable('+1 hour');
        $this->cronHelper->expects($this->once())
            ->method('computeNextRun')
            ->willReturn($nextRun);

        $this->repository->expects($this->once())
            ->method('updateAfterRun');

        $this->assertSame(1, $this->service->tick());
    }

    /**
     * @return array<string, mixed>
     */
    private function makeJob(
        string $id,
        ?string $cronExpression,
        int $retryCount = 0,
        int $maxRetries = 3,
    ): array {
        return [
            'id' => $id,
            'agent_name' => 'test-agent',
            'job_name' => 'test-job',
            'skill_id' => 'test.skill',
            'payload' => '{}',
            'cron_expression' => $cronExpression,
            'timezone' => 'UTC',
            'retry_count' => $retryCount,
            'max_retries' => $maxRetries,
            'retry_delay_seconds' => 60,
        ];
    }
}
