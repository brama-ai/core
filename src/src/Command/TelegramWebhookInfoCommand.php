<?php

declare(strict_types=1);

namespace App\Command;

use App\Channel\ChannelManager;
use App\Telegram\Repository\TelegramBotRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:channel:webhook-info', description: 'Display webhook status for a channel bot')]
final class TelegramWebhookInfoCommand extends Command
{
    public function __construct(
        private readonly TelegramBotRepository $botRepository,
        private readonly ChannelManager $channelManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bot-id', InputArgument::REQUIRED, 'Bot ID')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Channel type', 'telegram');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $botId = (string) $input->getArgument('bot-id');
        $channelType = (string) $input->getOption('type');

        $bot = $this->botRepository->findById($botId);
        if (!$bot) {
            $output->writeln(sprintf('<error>Bot "%s" not found</error>', $botId));

            return Command::FAILURE;
        }

        try {
            $result = $this->channelManager->adminAction($channelType, $botId, 'webhook-info', [
                'token' => (string) ($bot['bot_token'] ?? ''),
            ]);
            $success = (bool) ($result['result']['success'] ?? false);

            if (!$success) {
                $output->writeln(sprintf('<error>Failed: %s</error>', $result['result']['error'] ?? $result['error'] ?? 'unknown'));

                return Command::FAILURE;
            }

            /** @var array<string, mixed> $info */
            $info = $result['result']['webhook_info'] ?? [];
            $username = (string) ($bot['bot_username'] ?? $bot['channel_username'] ?? $botId);

            $output->writeln(sprintf('<info>Bot:</info> @%s (%s)', $username, $botId));
            $output->writeln(sprintf('<info>URL:</info> %s', $info['url'] ?? '(not set)'));
            $output->writeln(sprintf('<info>Pending updates:</info> %d', $info['pending_update_count'] ?? 0));
            $output->writeln(sprintf('<info>Max connections:</info> %d', $info['max_connections'] ?? 0));

            if (isset($info['last_error_date'])) {
                $output->writeln(sprintf('<error>Last error:</error> %s at %s',
                    $info['last_error_message'] ?? 'unknown',
                    date('Y-m-d H:i:s', (int) $info['last_error_date']),
                ));
            }

            if (isset($info['allowed_updates'])) {
                $output->writeln(sprintf('<info>Allowed updates:</info> %s', implode(', ', (array) $info['allowed_updates'])));
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
