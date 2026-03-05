<?php

declare(strict_types=1);

namespace App\Command;

use App\Logging\LogIndexManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'logs:index:setup', description: 'Create OpenSearch index template and today\'s log index')]
final class LogsIndexSetupCommand extends Command
{
    public function __construct(
        private readonly LogIndexManager $indexManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Setting up index template');
        if ($this->indexManager->setupTemplate()) {
            $io->success('Index template created/updated.');
        } else {
            $io->error('Failed to create index template.');

            return Command::FAILURE;
        }

        $io->section('Ensuring today\'s index exists');
        if ($this->indexManager->ensureTodayIndex()) {
            $io->success('Today\'s index is ready.');
        } else {
            $io->error('Failed to create today\'s index.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
