<?php

declare(strict_types=1);

namespace App\Command;

use App\A2AGateway\A2AClientInterface;
use App\Channel\Command\PlatformCommandRouter;
use App\Channel\ConversationTracker;
use App\Channel\DTO\NormalizedChat;
use App\Channel\DTO\NormalizedEvent;
use App\Channel\DTO\NormalizedMessage;
use App\Channel\DTO\NormalizedSender;
use App\Channel\EventBus\ChannelEventPublisher;
use App\Telegram\Repository\TelegramBotRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:channel:poll', description: 'Poll channel for updates (local development mode)')]
final class TelegramPollCommand extends Command implements SignalableCommandInterface
{
    private bool $running = true;

    private const TELEGRAM_API_BASE = 'https://api.telegram.org/bot';

    public function __construct(
        private readonly TelegramBotRepository $botRepository,
        private readonly ConversationTracker $conversationTracker,
        private readonly ChannelEventPublisher $eventPublisher,
        private readonly PlatformCommandRouter $commandRouter,
        private readonly A2AClientInterface $a2a,
        private readonly HttpClientInterface $httpClient,
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
        $this->logger->info('Channel poll command received signal, stopping gracefully', ['signal' => $signal]);

        return false;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('bot-id', InputArgument::REQUIRED, 'Bot ID to poll for')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Channel type', 'telegram')
            ->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Polling interval in seconds', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $botId = (string) $input->getArgument('bot-id');
        $channelType = (string) $input->getOption('type');
        $interval = (int) $input->getOption('interval');

        $bot = $this->botRepository->findById($botId);
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

        $username = (string) ($bot['bot_username'] ?? $bot['channel_username'] ?? $botId);
        $output->writeln(sprintf('<info>Polling for bot "%s" (@%s), channel: %s, interval: %ds</info>', $botId, $username, $channelType, $interval));

        while ($this->running) {
            if (\function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $result = $this->getUpdates($token, $offset);

            if (!($result['ok'] ?? false)) {
                $this->logger->error('getUpdates failed', [
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

                $events = $this->normalizeUpdate($update, $botId, $channelType);

                foreach ($events as $event) {
                    try {
                        $this->conversationTracker->track($channelType, $event);

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
                $this->botRepository->updateLastUpdateId($botId, $updateId);
            }

            if ([] === $updates) {
                sleep($interval);
            }
        }

        $output->writeln('<info>Polling stopped.</info>');

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function getUpdates(string $token, int $offset): array
    {
        try {
            $response = $this->httpClient->request('POST', self::TELEGRAM_API_BASE.$token.'/getUpdates', [
                'json' => [
                    'offset' => $offset,
                    'timeout' => 30,
                    'allowed_updates' => ['message', 'edited_message', 'channel_post', 'edited_channel_post', 'callback_query'],
                ],
                'timeout' => 35,
            ]);

            /** @var array<string, mixed> $data */
            $data = $response->toArray(false);

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('HTTP error during getUpdates', ['error' => $e->getMessage()]);

            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * Normalize a raw Telegram update via the channel agent A2A skill.
     * Falls back to basic normalization if agent is unavailable.
     *
     * @param array<string, mixed> $update
     *
     * @return list<NormalizedEvent>
     */
    private function normalizeUpdate(array $update, string $botId, string $channelType): array
    {
        $traceId = bin2hex(random_bytes(16));
        $requestId = bin2hex(random_bytes(8));

        try {
            $result = $this->a2a->invoke(
                'channel.normalizeInbound',
                [
                    'rawPayload' => $update,
                    'channelId' => $botId,
                    'headers' => [],
                ],
                $traceId,
                $requestId,
            );

            if ('failed' === ($result['status'] ?? null)) {
                return [];
            }

            /** @var array<string, mixed> $resultData */
            $resultData = $result['result'] ?? $result;

            return $this->buildNormalizedEvents($resultData, $channelType, $botId, $traceId, $requestId);
        } catch (\Throwable $e) {
            $this->logger->warning('Agent normalization failed, skipping update', [
                'error' => $e->getMessage(),
                'bot_id' => $botId,
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $normalizedData
     *
     * @return list<NormalizedEvent>
     */
    private function buildNormalizedEvents(
        array $normalizedData,
        string $channelType,
        string $channelId,
        string $traceId,
        string $requestId,
    ): array {
        $eventDataList = isset($normalizedData['events']) && is_array($normalizedData['events'])
            ? $normalizedData['events']
            : [$normalizedData];

        $events = [];
        foreach ($eventDataList as $eventData) {
            if (!is_array($eventData) || !isset($eventData['event_type'])) {
                continue;
            }

            $events[] = $this->buildNormalizedEvent($eventData, $channelType, $channelId, $traceId, $requestId);
        }

        return $events;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildNormalizedEvent(
        array $data,
        string $channelType,
        string $channelId,
        string $traceId,
        string $requestId,
    ): NormalizedEvent {
        /** @var array<string, mixed> $chatData */
        $chatData = is_array($data['chat'] ?? null) ? $data['chat'] : [];
        /** @var array<string, mixed> $senderData */
        $senderData = is_array($data['sender'] ?? null) ? $data['sender'] : [];
        /** @var array<string, mixed> $messageData */
        $messageData = is_array($data['message'] ?? null) ? $data['message'] : [];

        $chat = new NormalizedChat(
            id: (string) ($chatData['id'] ?? ''),
            type: (string) ($chatData['type'] ?? 'unknown'),
            title: isset($chatData['title']) ? (string) $chatData['title'] : null,
            threadId: isset($chatData['thread_id']) ? (string) $chatData['thread_id'] : null,
        );

        $sender = new NormalizedSender(
            id: (string) ($senderData['id'] ?? ''),
            username: isset($senderData['username']) ? (string) $senderData['username'] : null,
            firstName: isset($senderData['first_name']) ? (string) $senderData['first_name'] : null,
            isBot: (bool) ($senderData['is_bot'] ?? false),
        );

        $message = new NormalizedMessage(
            id: (string) ($messageData['id'] ?? $messageData['message_id'] ?? ''),
            text: isset($messageData['text']) ? (string) $messageData['text'] : null,
            commandName: isset($messageData['command_name']) ? (string) $messageData['command_name'] : null,
            commandArgs: isset($messageData['command_args']) ? (string) $messageData['command_args'] : null,
        );

        return new NormalizedEvent(
            eventType: (string) ($data['event_type'] ?? 'message_received'),
            platform: $channelType,
            botId: $channelId,
            chat: $chat,
            sender: $sender,
            message: $message,
            traceId: (string) ($data['trace_id'] ?? $traceId),
            requestId: (string) ($data['request_id'] ?? $requestId),
            rawUpdateId: (int) ($data['raw_update_id'] ?? 0),
        );
    }
}
