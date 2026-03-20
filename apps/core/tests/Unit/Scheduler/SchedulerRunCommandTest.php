<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Command\SchedulerRunCommand;
use App\Scheduler\AsyncA2ADispatcherInterface;
use App\Scheduler\CronExpressionHelperInterface;
use App\Scheduler\ScheduledJobRepositoryInterface;
use App\Scheduler\SchedulerJobLogRepositoryInterface;
use App\Scheduler\SchedulerService;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Unit tests for SchedulerRunCommand.
 *
 * SchedulerService is final, so we build a real instance with mocked
 * dependencies. The repository mock controls which jobs appear due,
 * letting us test the command's loop, signal handling, logging,
 * and DB keepalive behaviour.
 */
final class SchedulerRunCommandTest extends Unit
{
    private ScheduledJobRepositoryInterface&MockObject $repository;
    private Connection&MockObject $connection;
    private LoggerInterface&MockObject $logger;
    private SchedulerRunCommand $command;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(ScheduledJobRepositoryInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $cronHelper = $this->createMock(CronExpressionHelperInterface::class);
        $asyncDispatcher = $this->createMock(AsyncA2ADispatcherInterface::class);
        $asyncDispatcher->method('dispatchAll')->willReturn([]);
        $jobLog = $this->createMock(SchedulerJobLogRepositoryInterface::class);
        $jobLog->method('logStart')->willReturn('log-stub');

        $service = new SchedulerService(
            $this->repository,
            $cronHelper,
            $asyncDispatcher,
            $this->logger,
            $this->connection,
            $jobLog,
        );

        $this->command = new SchedulerRunCommand($service, $this->connection, $this->logger, pollIntervalSeconds: 0);
    }

    public function testSignalStopsLoop(): void
    {
        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturnCallback(function (): array {
                $this->command->handleSignal(\SIGTERM);

                return [];
            });

        $output = new BufferedOutput();
        $result = $this->command->run(new ArrayInput([]), $output);

        $this->assertSame(0, $result);
        $text = $output->fetch();
        $this->assertStringContainsString('Scheduler started', $text);
        $this->assertStringContainsString('Scheduler stopped gracefully', $text);
    }

    public function testSubscribedSignals(): void
    {
        $signals = $this->command->getSubscribedSignals();

        $this->assertContains(\SIGTERM, $signals);
        $this->assertContains(\SIGINT, $signals);
    }

    public function testHandleSignalReturnsFalse(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('signal'),
                $this->callback(fn (array $ctx): bool => \SIGTERM === $ctx['signal']),
            );

        $result = $this->command->handleSignal(\SIGTERM);

        $this->assertFalse($result);
    }

    public function testTickErrorIsLoggedAndLoopContinues(): void
    {
        $calls = 0;
        $this->repository->expects($this->exactly(2))
            ->method('findDueJobs')
            ->willReturnCallback(function () use (&$calls): array {
                ++$calls;
                if (1 === $calls) {
                    throw new \RuntimeException('DB connection lost');
                }
                $this->command->handleSignal(\SIGTERM);

                return [];
            });

        $this->logger->expects($this->atLeastOnce())
            ->method('error');

        $output = new BufferedOutput();
        $this->command->run(new ArrayInput([]), $output);

        $text = $output->fetch();
        $this->assertStringContainsString('Tick error: DB connection lost', $text);
        $this->assertStringContainsString('Scheduler stopped gracefully', $text);
    }

    public function testExecutedJobsOutputsCount(): void
    {
        $this->repository->expects($this->once())
            ->method('findDueJobs')
            ->willReturnCallback(function (): array {
                $this->command->handleSignal(\SIGTERM);

                return [
                    ['id' => 'j1', 'agent_name' => 'a', 'job_name' => 'j', 'skill_id' => 's', 'payload' => '{}', 'cron_expression' => null, 'timezone' => 'UTC', 'retry_count' => 0, 'max_retries' => 1, 'retry_delay_seconds' => 60],
                    ['id' => 'j2', 'agent_name' => 'a', 'job_name' => 'j2', 'skill_id' => 's', 'payload' => '{}', 'cron_expression' => null, 'timezone' => 'UTC', 'retry_count' => 0, 'max_retries' => 1, 'retry_delay_seconds' => 60],
                ];
            });

        $output = new BufferedOutput();
        $this->command->run(new ArrayInput([]), $output);

        $text = $output->fetch();
        $this->assertStringContainsString('Executed 2 job(s)', $text);
    }

    public function testEnsureConnectionReconnectsOnFailure(): void
    {
        $callCount = 0;
        $this->connection->expects($this->atLeastOnce())
            ->method('executeQuery')
            ->willReturnCallback(function () use (&$callCount) {
                ++$callCount;
                if (1 === $callCount) {
                    throw new \RuntimeException('Connection gone');
                }

                return $this->createMock(\Doctrine\DBAL\Result::class);
            });

        $this->connection->expects($this->once())
            ->method('close');

        $tickCount = 0;
        $this->repository->expects($this->exactly(2))
            ->method('findDueJobs')
            ->willReturnCallback(function () use (&$tickCount): array {
                ++$tickCount;
                if (2 === $tickCount) {
                    $this->command->handleSignal(\SIGTERM);
                }

                return [];
            });

        $output = new BufferedOutput();
        $this->command->run(new ArrayInput([]), $output);

        $text = $output->fetch();
        $this->assertStringContainsString('Scheduler stopped gracefully', $text);
    }
}
