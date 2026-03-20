<?php

declare(strict_types=1);

namespace App\Command;

use App\CoderAgent\CoderWorkerRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'coder:worker:stop', description: 'Request graceful stop for a coder worker')]
final class CoderWorkerStopCommand extends Command
{
    public function __construct(
        private readonly CoderWorkerRepositoryInterface $workers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Worker ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workerId = (string) $input->getArgument('id');
        $worker = $this->workers->findById($workerId);
        if (null === $worker) {
            $output->writeln('<error>Worker not found.</error>');

            return Command::FAILURE;
        }

        $this->workers->requestStop($workerId);
        $output->writeln(sprintf('Stop requested for %s.', $workerId));

        return Command::SUCCESS;
    }
}
