<?php

declare(strict_types=1);

namespace App\Command;

use App\Telegram\Api\TelegramApiClient;
use App\Telegram\Command\TelegramCommandRouter;
use App\Telegram\EventBus\TelegramEventPublisher;
use App\Telegram\Service\TelegramBotRegistry;
use App\Telegram\Service\TelegramChatTracker;
use App\Telegram\Service\TelegramUpdateNormalizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:telegram:poll', description: 'Poll Telegram for updates (local development mode)')]
final class TelegramPollCommand extends Command implements SignalableCommandInterface
{
    private bool $running = true;

    public function __construct(
        private readonly TelegramApiClient $apiClient,
        private readonly TelegramBotRegistry $botRegistry,
        private readonly TelegramUpdateNormalizer $normalizer,
        private readonly TelegramChatTracker $chatTracker,
        private readonly TelegramEventPublisher $eventPublisher,
        private readonly TelegramCommandRouter $commandRouter,
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
        $this->running = false;
        $this->logger->info('Telegram poll command received signal, stopping gracefully', ['signal' => $signal]);

        return false;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bot-id', InputArgument::REQUIRED, 'Bot ID to poll for')
            ->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Polling interval in seconds', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $botId = (string) $input->getArgument('bot-id');
        $interval = (int) $input->getOption('interval');

        $bot = $this->botRegistry->getBot($botId);
        if (!$bot) {
            $output->writeln(sprintf('<error>Bot "%s" not found</error>', $botId));

            return Command::FAILURE;
        }

        $token = (string) $bot['bot_token'];
        $offset = ((int) ($bot['last_update_id'] ?? 0)) + 1;

        // Register signal handlers for graceful shutdown
        if (\function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () use ($output): void {
                $output->writeln("\n<info>Shutting down...</info>");
                $this->running = false;
            });
            pcntl_signal(SIGTERM, function () use ($output): void {
                $output->writeln("\n<info>Shutting down...</info>");
                $this->running = false;
            });
        }

        $output->writeln(sprintf('<info>Polling for bot "%s" (@%s), interval: %ds</info>', $botId, $bot['bot_username'], $interval));

        while ($this->running) {
            if (\function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $result = $this->apiClient->getUpdates($token, [
                'offset' => $offset,
                'timeout' => 30,
                'allowed_updates' => ['message', 'edited_message', 'channel_post', 'edited_channel_post', 'callback_query'],
            ]);

            if (!($result['ok'] ?? false)) {
                $this->logger->error('Telegram getUpdates failed', [
                    'bot_id' => $botId,
                    'error' => $result['description'] ?? 'unknown',
                ]);
                sleep($interval);

                continue;
            }

            $updates = (array) ($result['result'] ?? []);

            foreach ($updates as $update) {
                if (!is_array($update)) {
                    continue;
                }

                $updateId = (int) ($update['update_id'] ?? 0);
                $output->writeln(sprintf('  Update #%d received', $updateId), OutputInterface::VERBOSITY_VERBOSE);

                $events = $this->normalizer->normalize($update, $botId);

                foreach ($events as $event) {
                    try {
                        $this->chatTracker->track($event, $botId);

                        if ('command_received' === $event->eventType) {
                            $this->commandRouter->route($event);
                        }

                        $this->eventPublisher->publish($event);

                        $output->writeln(sprintf('  [%s] %s from %s in %s',
                            $event->eventType,
                            $event->message->text ?? '(no text)',
                            $event->sender->username ?? $event->sender->id,
                            $event->chat->title ?? $event->chat->id,
                        ));
                    } catch (\Throwable $e) {
                        $this->logger->error('Failed to process polled event', [
                            'error' => $e->getMessage(),
                            'event_type' => $event->eventType,
                        ]);
                        $output->writeln(sprintf('  <error>Error: %s</error>', $e->getMessage()));
                    }
                }

                $offset = $updateId + 1;
                $this->botRegistry->updateLastUpdateId($botId, $updateId);
            }

            if ([] === $updates) {
                sleep($interval);
            }
        }

        $output->writeln('<info>Polling stopped.</info>');

        return Command::SUCCESS;
    }
}
