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

#[AsCommand(name: 'app:channel:set-webhook', description: 'Register webhook for a channel bot', aliases: ['app:telegram:set-webhook'])]
final class TelegramWebhookCommand extends Command
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
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Channel type', 'telegram')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'Webhook base URL (e.g., https://example.com)')
            ->addOption('max-connections', null, InputOption::VALUE_OPTIONAL, 'Max simultaneous connections', '40');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $botId = (string) $input->getArgument('bot-id');
        $channelType = (string) $input->getOption('type');
        $baseUrl = (string) $input->getOption('url');
        $maxConnections = (int) $input->getOption('max-connections');

        $bot = $this->botRepository->findById($botId);
        if (!$bot) {
            $output->writeln(sprintf('<error>Bot "%s" not found</error>', $botId));

            return Command::FAILURE;
        }

        $webhookUrl = rtrim($baseUrl, '/').'/api/v1/webhook/'.$channelType.'/'.$botId;
        $token = (string) ($bot['bot_token'] ?? '');
        $secret = (string) ($bot['webhook_secret'] ?? '');

        $params = [
            'token' => $token,
            'url' => $webhookUrl,
            'max_connections' => $maxConnections,
        ];

        if ('' !== $secret) {
            $params['secret'] = $secret;
        }

        try {
            $result = $this->channelManager->adminAction($channelType, $botId, 'set-webhook', $params);
            $success = (bool) ($result['result']['success'] ?? false);

            if ($success) {
                $this->botRepository->update($botId, ['webhook_url' => $webhookUrl]);
                $output->writeln(sprintf('<info>Webhook set: %s</info>', $webhookUrl));

                return Command::SUCCESS;
            }

            $output->writeln(sprintf('<error>Failed: %s</error>', $result['result']['description'] ?? $result['error'] ?? 'unknown error'));

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
