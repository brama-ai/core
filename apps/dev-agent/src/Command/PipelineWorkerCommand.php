<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\DevTaskRepository;
use App\Service\PipelineRunner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'dev:pipeline:worker', description: 'Polls for pending tasks and runs pipeline')]
final class PipelineWorkerCommand extends Command
{
    public function __construct(
        private readonly DevTaskRepository $taskRepo,
        private readonly PipelineRunner $runner,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Pipeline worker started, polling for pending tasks...</info>');

        pcntl_async_signals(true);
        $running = true;

        pcntl_signal(\SIGTERM, static function () use (&$running, $output): void {
            $output->writeln('<comment>SIGTERM received, shutting down...</comment>');
            $running = false;
        });
        pcntl_signal(\SIGINT, static function () use (&$running, $output): void {
            $output->writeln('<comment>SIGINT received, shutting down...</comment>');
            $running = false;
        });

        while ($running) {
            try {
                $task = $this->taskRepo->findNextPending();

                if (null !== $task) {
                    $taskId = (int) $task['id'];
                    $output->writeln(\sprintf('<info>Running pipeline for task #%d: %s</info>', $taskId, $task['title']));

                    try {
                        $this->runner->run($taskId);
                        $output->writeln(\sprintf('<info>Task #%d completed</info>', $taskId));
                    } catch (\Throwable $e) {
                        $this->logger->error('Pipeline failed', [
                            'task_id' => $taskId,
                            'error' => $e->getMessage(),
                        ]);
                        $this->taskRepo->updateStatus($taskId, 'failed', [
                            'error_message' => $e->getMessage(),
                            'finished_at' => 'now()',
                        ]);
                        $output->writeln(\sprintf('<error>Task #%d failed: %s</error>', $taskId, $e->getMessage()));
                    }
                } else {
                    sleep(5);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Worker loop error', ['error' => $e->getMessage()]);
                sleep(10);
            }
        }

        $output->writeln('<info>Pipeline worker stopped</info>');

        return Command::SUCCESS;
    }
}
