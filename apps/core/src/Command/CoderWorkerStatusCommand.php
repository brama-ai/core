<?php

declare(strict_types=1);

namespace App\Command;

use App\CoderAgent\CoderWorkerRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'coder:worker:status', description: 'Show coder worker status')]
final class CoderWorkerStatusCommand extends Command
{
    public function __construct(
        private readonly CoderWorkerRepositoryInterface $workers,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workers = $this->workers->findAll();
        if ([] === $workers) {
            $output->writeln('No coder workers registered.');

            return Command::SUCCESS;
        }

        foreach ($workers as $worker) {
            $output->writeln(sprintf(
                '%s  status=%s  task=%s  heartbeat=%s',
                $worker['id'],
                $worker['status'],
                $worker['current_task_id'] ?? '-',
                $worker['last_heartbeat_at'] ?? '-',
            ));
        }

        return Command::SUCCESS;
    }
}
