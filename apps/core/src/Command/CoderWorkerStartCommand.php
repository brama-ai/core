<?php

declare(strict_types=1);

namespace App\Command;

use App\CoderAgent\CoderWorkerRepositoryInterface;
use App\CoderAgent\CoderWorkerService;
use App\CoderAgent\WorkerStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'coder:worker:start', description: 'Start a coder worker loop')]
final class CoderWorkerStartCommand extends Command implements SignalableCommandInterface
{
    private bool $stop = false;

    public function __construct(
        private readonly CoderWorkerRepositoryInterface $workers,
        private readonly CoderWorkerService $service,
        private readonly int $maxWorkers,
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
        $this->stop = true;
        $this->logger->info('Coder worker received signal, stopping gracefully', ['signal' => $signal]);

        return false;
    }

    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Worker ID', 'worker-1')
            ->addOption('once', null, InputOption::VALUE_NONE, 'Process at most one task and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workerId = (string) $input->getOption('id');
        $once = (bool) $input->getOption('once');

        if (\count(array_filter($this->workers->findAll(), static fn (array $worker): bool => \in_array((string) $worker['status'], [WorkerStatus::Idle->value, WorkerStatus::Busy->value, WorkerStatus::Stopping->value], true))) >= $this->maxWorkers
            && null === $this->workers->findById($workerId)) {
            $output->writeln('<error>Worker limit reached.</error>');

            return Command::FAILURE;
        }

        $this->workers->register($workerId, getmypid() ?: 0);
        $output->writeln(sprintf('Coder worker %s started.', $workerId));
        $this->logger->info('Coder worker started', ['worker_id' => $workerId]);

        while (!$this->stop) {
            $worker = $this->workers->findById($workerId);
            if (null !== $worker && WorkerStatus::Stopping->value === (string) $worker['status']) {
                $output->writeln(sprintf('Worker %s received stop request, shutting down gracefully.', $workerId));
                $this->logger->info('Worker received stop request', ['worker_id' => $workerId]);
                break;
            }

            $this->workers->heartbeat($workerId, WorkerStatus::Idle);

            try {
                $task = $this->service->runNextTask($workerId);
                if (null !== $task) {
                    $output->writeln(sprintf('[%s] processed task %s (%s)', date('Y-m-d H:i:s'), $task['id'], $task['status']));
                    $this->logger->info('Task processed', ['worker_id' => $workerId, 'task_id' => $task['id'], 'status' => $task['status']]);
                }
            } catch (\Throwable $e) {
                $output->writeln(sprintf('[%s] Task processing error: %s', date('Y-m-d H:i:s'), $e->getMessage()));
                $this->logger->error('Task processing failed', ['worker_id' => $workerId, 'exception' => $e]);

                // Continue processing other tasks even if one fails
            }

            if ($once) {
                break;
            }

            // Check for stop signal more frequently during sleep
            // @phpstan-ignore-next-line booleanNot.alwaysTrue (stop can be set to true by signal handler)
            for ($i = 0; $i < 3 && !$this->stop; ++$i) {
                sleep(1);
            }
        }

        $this->workers->markStopped($workerId);
        $output->writeln(sprintf('Coder worker %s stopped gracefully.', $workerId));
        $this->logger->info('Coder worker stopped', ['worker_id' => $workerId]);

        return Command::SUCCESS;
    }
}
