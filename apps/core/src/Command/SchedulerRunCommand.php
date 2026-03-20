<?php

declare(strict_types=1);

namespace App\Command;

use App\Scheduler\SchedulerService;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'scheduler:run', description: 'Run the central scheduler polling loop')]
final class SchedulerRunCommand extends Command implements SignalableCommandInterface
{
    private const DEFAULT_POLL_INTERVAL_SECONDS = 10;

    private bool $shouldStop = false;

    public function __construct(
        private readonly SchedulerService $schedulerService,
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly int $pollIntervalSeconds = self::DEFAULT_POLL_INTERVAL_SECONDS,
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
        $this->shouldStop = true;
        $this->logger->info('Scheduler received signal, stopping after current tick', ['signal' => $signal]);

        return false;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Scheduler started. Polling every '.$this->pollIntervalSeconds.' seconds.');
        $this->logger->info('Scheduler started');

        while (!$this->shouldStop) {
            try {
                $this->ensureConnection();
                $executed = $this->schedulerService->tick();

                if ($executed > 0) {
                    $output->writeln(sprintf('[%s] Executed %d job(s).', date('Y-m-d H:i:s'), $executed));
                    $this->logger->info('Scheduler tick completed', ['executed' => $executed]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Scheduler tick failed', ['exception' => $e]);
                $output->writeln(sprintf('[%s] Tick error: %s', date('Y-m-d H:i:s'), $e->getMessage()));
            }

            if ($this->shouldStop) {
                break;
            }

            sleep($this->pollIntervalSeconds);
        }

        $output->writeln('Scheduler stopped gracefully.');
        $this->logger->info('Scheduler stopped');

        return Command::SUCCESS;
    }

    private function ensureConnection(): void
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $this->connection->close();
            $this->logger->info('Scheduler DB connection reset, will reconnect on next query');
        }
    }
}
