<?php

declare(strict_types=1);

namespace App\Command;

use App\Scheduler\SchedulerService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'scheduler:run', description: 'Run the central job scheduler (long-running process)')]
final class SchedulerRunCommand extends Command implements SignalableCommandInterface
{
    private const TICK_INTERVAL_SECONDS = 10;

    private bool $shouldStop = false;

    public function __construct(
        private readonly SchedulerService $schedulerService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        return [\SIGTERM, \SIGINT];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->logger->info('Scheduler received signal, stopping gracefully', ['signal' => $signal]);
        $this->shouldStop = true;

        return false;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Scheduler started. Press Ctrl+C to stop.');
        $this->logger->info('Scheduler started');

        while (!$this->shouldStop) {
            try {
                $executed = $this->schedulerService->tick();

                if ($executed > 0) {
                    $output->writeln(sprintf('[%s] Executed %d job(s)', date('Y-m-d H:i:s'), $executed));
                    $this->logger->info('Scheduler tick completed', ['executed' => $executed]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Scheduler tick failed', ['error' => $e->getMessage(), 'exception' => $e]);
                $output->writeln(sprintf('[%s] Tick error: %s', date('Y-m-d H:i:s'), $e->getMessage()));
            }

            sleep(self::TICK_INTERVAL_SECONDS);
        }

        $output->writeln('Scheduler stopped.');
        $this->logger->info('Scheduler stopped');

        return Command::SUCCESS;
    }
}
