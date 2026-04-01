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

#[AsCommand(name: 'app:channel:delete-webhook', description: 'Delete webhook for a channel bot')]
final class TelegramDeleteWebhookCommand extends Command
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
            $result = $this->channelManager->adminAction($channelType, $botId, 'delete-webhook', [
                'token' => (string) ($bot['bot_token'] ?? ''),
            ]);
            $success = (bool) ($result['result']['success'] ?? false);

            if ($success) {
                $this->botRepository->update($botId, ['webhook_url' => null]);
                $output->writeln('<info>Webhook deleted.</info>');

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
