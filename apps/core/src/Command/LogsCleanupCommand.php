<?php

declare(strict_types=1);

namespace App\Command;

use App\Logging\LogIndexManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'logs:cleanup', description: 'Delete old log indices based on retention policy')]
final class LogsCleanupCommand extends Command
{
    public function __construct(
        private readonly LogIndexManager $indexManager,
        private readonly int $defaultRetentionDays,
        private readonly int $defaultMaxSizeGb,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Max age in days', (string) $this->defaultRetentionDays)
            ->addOption('max-size-gb', null, InputOption::VALUE_REQUIRED, 'Max total size in GB', (string) $this->defaultMaxSizeGb)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $maxAge = (int) $input->getOption('max-age');
        $maxSizeGb = (int) $input->getOption('max-size-gb');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('DRY RUN — no indices will be deleted.');
        }

        $indices = $this->indexManager->listLogIndices();
        if ([] === $indices) {
            $io->success('No log indices found.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d log index(es).', \count($indices)));

        $cutoffDate = (new \DateTimeImmutable())->modify(sprintf('-%d days', $maxAge))->format('Y-m-d');
        $deleted = 0;

        foreach ($indices as $entry) {
            if ($entry['date'] < $cutoffDate) {
                $io->text(sprintf('  [age] %s (date: %s, before cutoff: %s)', $entry['index'], $entry['date'], $cutoffDate));

                if (!$dryRun) {
                    $this->indexManager->deleteIndex($entry['index']);
                }

                ++$deleted;
            }
        }

        $remaining = array_filter($indices, static fn (array $e): bool => $e['date'] >= $cutoffDate);
        $totalBytes = array_sum(array_column($remaining, 'size_bytes'));
        $totalGb = $totalBytes / (1024 ** 3);
        $maxSizeBytes = $maxSizeGb * (1024 ** 3);

        if ($totalBytes > $maxSizeBytes) {
            $io->text(sprintf('  Total size %.2f GB exceeds limit %d GB — removing oldest indices.', $totalGb, $maxSizeGb));

            usort($remaining, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

            foreach ($remaining as $entry) {
                if ($totalBytes <= $maxSizeBytes) {
                    break;
                }

                $io->text(sprintf('  [size] %s (%.2f MB)', $entry['index'], $entry['size_bytes'] / (1024 ** 2)));

                if (!$dryRun) {
                    $this->indexManager->deleteIndex($entry['index']);
                }

                $totalBytes -= $entry['size_bytes'];
                ++$deleted;
            }
        }

        $action = $dryRun ? 'Would delete' : 'Deleted';
        $io->success(sprintf('%s %d index(es).', $action, $deleted));

        return Command::SUCCESS;
    }
}
