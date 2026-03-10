<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\A2AGateway\A2AClientInterface;
use App\Scheduler\CronExpressionHelper;
use App\Scheduler\ScheduledJobRepository;
use App\Scheduler\SchedulerService;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

final class SchedulerServiceTest extends Unit
{
    private ScheduledJobRepository&MockObject $repository;
    private CronExpressionHelper&MockObject $cronHelper;
    private A2AClientInterface&MockObject $a2aClient;
    private LoggerInterface&MockObject $logger;
    private SchedulerService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ScheduledJobRepository::class);
        $this->cronHelper = $this->createMock(CronExpressionHelper::class);
        $this->a2aClient = $this->createMock(A2AClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new SchedulerService(
            $this->repository,
            $this->cronHelper,
            $this->a2aClient,
            $this->logger,
        );
    }

    public function testTickReturnsZeroWhenNoJobsDue(): void
    {
        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([]);

        $this->a2aClient->expects($this->never())->method('invoke');

        $count = $this->service->tick();

        $this->assertSame(0, $count);
    }

    public function testTickExecutesJobAndUpdatesAfterSuccess(): void
    {
        $job = $this->makeJob('job-1', 'test.skill', '* * * * *');

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'completed']);

        $nextRun = new \DateTimeImmutable('+1 minute');
        $this->cronHelper->expects($this->once())
            ->method('computeNextRun')
            ->with('* * * * *', 'UTC')
            ->willReturn($nextRun);

        $this->repository->expects($this->once())
            ->method('updateAfterRun')
            ->with('job-1', 'completed', $this->isString());

        $count = $this->service->tick();

        $this->assertSame(1, $count);
    }

    public function testTickHandlesRetryOnFailure(): void
    {
        $job = $this->makeJob('job-2', 'test.skill', '* * * * *', retryCount: 0, maxRetries: 3);

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'failed', 'reason' => 'agent_error']);

        $this->repository->expects($this->once())
            ->method('updateRetry')
            ->with('job-2', 1, $this->isString());

        $this->repository->expects($this->never())->method('disableJob');

        $this->service->tick();
    }

    public function testTickDeadLettersJobWhenMaxRetriesExceeded(): void
    {
        $job = $this->makeJob('job-3', 'test.skill', '* * * * *', retryCount: 2, maxRetries: 3);

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'failed', 'reason' => 'agent_error']);

        $this->repository->expects($this->once())
            ->method('disableJob')
            ->with('job-3');

        $this->repository->expects($this->never())->method('updateRetry');

        $this->service->tick();
    }

    public function testTickHandlesOneShotJobWithNullNextRun(): void
    {
        $job = $this->makeJob('job-4', 'test.skill', null);

        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturn([$job]);

        $this->a2aClient->expects($this->once())
            ->method('invoke')
            ->willReturn(['status' => 'completed']);

        $this->cronHelper->expects($this->never())->method('computeNextRun');

        $this->repository->expects($this->once())
            ->method('updateAfterRun')
            ->with('job-4', 'completed', null);

        $this->service->tick();
    }

    public function testTickHandlesCatchUpPolicy(): void
    {
        // A job with next_run_at in the past (scheduler was down) should be executed
        $job = $this->makeJob('job-5', 'test.skill', '0 * * * *');
        $job['next_run_at'] = '2020-01-01 00:00:00+00';

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
            ->method('updateAfterRun')
            ->with('job-5', 'completed', $this->isString());

        $count = $this->service->tick();

        $this->assertSame(1, $count);
    }

    public function testRegisterFromManifestSkipsManifestWithoutScheduledJobs(): void
    {
        $manifest = ['name' => 'test-agent', 'version' => '1.0.0'];

        $this->repository->expects($this->never())->method('registerJob');

        $count = $this->service->registerFromManifest('test-agent', $manifest);

        $this->assertSame(0, $count);
    }

    public function testRegisterFromManifestRegistersJobs(): void
    {
        $manifest = [
            'name' => 'test-agent',
            'scheduled_jobs' => [
                [
                    'job_name' => 'crawl_pipeline',
                    'skill_id' => 'news_maker.trigger_crawl',
                    'cron' => '0 */4 * * *',
                    'payload' => [],
                    'max_retries' => 3,
                    'retry_delay_seconds' => 120,
                ],
            ],
        ];

        $nextRun = new \DateTimeImmutable('+4 hours');
        $this->cronHelper->expects($this->once())
            ->method('computeNextRun')
            ->with('0 */4 * * *', 'UTC')
            ->willReturn($nextRun);

        $this->repository->expects($this->once())
            ->method('registerJob')
            ->with(
                'test-agent',
                'crawl_pipeline',
                'news_maker.trigger_crawl',
                [],
                '0 */4 * * *',
                $this->isString(),
                3,
                120,
                'UTC',
            );

        $count = $this->service->registerFromManifest('test-agent', $manifest);

        $this->assertSame(1, $count);
    }

    public function testRegisterFromManifestSkipsInvalidJobDefinitions(): void
    {
        $manifest = [
            'name' => 'test-agent',
            'scheduled_jobs' => [
                ['job_name' => '', 'skill_id' => 'some.skill'],  // empty job_name
                ['job_name' => 'valid-job', 'skill_id' => ''],   // empty skill_id
                'not-an-array',                                    // not an array
            ],
        ];

        $this->repository->expects($this->never())->method('registerJob');

        $count = $this->service->registerFromManifest('test-agent', $manifest);

        $this->assertSame(0, $count);
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function makeJob(
        string $id,
        string $skillId,
        ?string $cronExpression,
        int $retryCount = 0,
        int $maxRetries = 3,
        array $extra = [],
    ): array {
        return array_merge([
            'id' => $id,
            'agent_name' => 'test-agent',
            'job_name' => 'test-job',
            'skill_id' => $skillId,
            'payload' => '{}',
            'cron_expression' => $cronExpression,
            'next_run_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            'last_run_at' => null,
            'last_status' => null,
            'retry_count' => $retryCount,
            'max_retries' => $maxRetries,
            'retry_delay_seconds' => 60,
            'enabled' => true,
            'timezone' => 'UTC',
        ], $extra);
    }
}
