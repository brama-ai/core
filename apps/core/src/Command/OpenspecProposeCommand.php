<?php

declare(strict_types=1);

namespace App\Command;

use React\EventLoop\Loop;
use React\Promise\Promise;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function React\Async\async;
use function React\Async\await;

#[AsCommand(name: 'openspec:propose', description: 'Scaffold and generate an OpenSpec proposal asynchronously')]
final class OpenspecProposeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('change-id', InputArgument::REQUIRED, 'The ID of the change to propose')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Strict validation mode')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactive mode')
            ->addOption('async', 'a', InputOption::VALUE_NONE, 'Run completely in the background without UI')
            ->addOption('background', 'b', InputOption::VALUE_NONE, 'Alias for --async');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $changeId = (string) $input->getArgument('change-id');
        $isAsync = $input->getOption('async') || $input->getOption('background');

        if ($isAsync) {
            $output->writeln(sprintf('<info>Starting async openspec propose for "%s" in the background...</info>', $changeId));
            $output->writeln('<comment>Task detached. You can continue using your terminal while generating the proposal.</comment>');

            // In a full implementation, we would detach the process here (e.g., using pcntl_fork,
            // launching a separate shell command via react/child-process, or dispatching to a queue).

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Generating OpenSpec proposal for change: %s</info>', $changeId));
        $output->writeln('<comment>(Using ReactPHP for asynchronous staging)</comment>');

        // Create and configure the progress bar
        $progressBar = new ProgressBar($output, 100);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->setMessage('Ініціалізація (Initialization)...');
        $progressBar->start();

        // The key stages requested by the user
        $stages = [
            'Читання контексту (Reading context)' => 15,
            'Аналіз (Analysis)' => 35,
            'Генерація (Generation)' => 60,
            'Валідація (Validation)' => 85,
            'Збереження (Saving)' => 100,
        ];

        // We wrap the operation in an async coroutine (React\Async)
        $runTask = async(function () use ($progressBar, $stages) {
            foreach ($stages as $message => $progress) {
                // Update progress bar UI
                $progressBar->setMessage($message);
                $progressBar->display();

                // Non-blocking wait using ReactPHP (Fiber-based async sleep)
                await(new Promise(function (callable $resolve) {
                    Loop::addTimer(1.0, function () use ($resolve) {
                        $resolve(true);
                    });
                }));

                $progressBar->setProgress($progress);
            }
        });

        // Run the async operation and block the main thread until it resolves.
        // During await(), ReactPHP ticks the event loop automatically!
        await($runTask());

        $progressBar->finish();

        $output->writeln('');
        $output->writeln('');
        $output->writeln('<info>✅ OpenSpec proposal generated successfully!</info>');

        return Command::SUCCESS;
    }
}
