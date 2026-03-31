<?php

declare(strict_types=1);

namespace App\Channel;

use App\Channel\DTO\NormalizedEvent;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;

/**
 * Channel-agnostic conversation tracker.
 *
 * Extracted from TelegramChatTracker. Works with the channel_conversations
 * table (currently telegram_chats with added channel_type column).
 *
 * Tracks conversation metadata for any channel type.
 */
final class ConversationTracker
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Track or update a conversation based on the normalized event.
     */
    public function track(string $channelType, NormalizedEvent $event): void
    {
        $chatId = $event->chat->id;
        if ('' === $chatId || '0' === $chatId) {
            return;
        }

        $now = new \DateTimeImmutable();

        // Handle bot join/leave
        if ('member_joined' === $event->eventType && $event->sender->isBot) {
            $this->handleBotJoined($channelType, $event->botId, $chatId, $event, $now);

            return;
        }

        if ('member_left' === $event->eventType && $event->sender->isBot) {
            $this->handleBotLeft($channelType, $event->botId, $chatId, $now);

            return;
        }

        // Upsert conversation on regular messages
        $this->ensureConversationExists($channelType, $event->botId, $chatId, $event);

        // Update activity timestamp
        $this->updateLastMessageTime($channelType, $event->botId, $chatId, $now);
    }

    /**
     * Find a tracked conversation by channel type and chat ID.
     *
     * @return array<string, mixed>|null
     */
    public function findConversation(string $channelType, string $chatId): ?array
    {
        $sql = <<<'SQL'
            SELECT * FROM telegram_chats
            WHERE channel_type = :channel_type AND chat_id = :chat_id
            ORDER BY joined_at DESC NULLS LAST
            LIMIT 1
        SQL;

        $row = $this->connection->fetchAssociative($sql, [
            'channel_type' => $channelType,
            'chat_id' => $chatId,
        ]);

        if (!$row) {
            return null;
        }

        return $this->hydrateConversation($row);
    }

    private function handleBotJoined(
        string $channelType,
        string $botId,
        string $chatId,
        NormalizedEvent $event,
        \DateTimeImmutable $now,
    ): void {
        $existing = $this->findByBotAndChatId($channelType, $botId, $chatId);

        if ($existing) {
            $this->connection->update(
                'telegram_chats',
                [
                    'title' => $event->chat->title,
                    'type' => $event->chat->type,
                    'has_threads' => null !== $event->chat->threadId,
                    'joined_at' => $now,
                    'left_at' => null,
                    'channel_type' => $channelType,
                ],
                ['id' => $existing['id']],
                [
                    'title' => Types::STRING,
                    'type' => Types::STRING,
                    'has_threads' => Types::BOOLEAN,
                    'joined_at' => Types::DATETIME_IMMUTABLE,
                    'left_at' => Types::DATETIME_IMMUTABLE,
                    'channel_type' => Types::STRING,
                ],
            );
        } else {
            $this->createConversation($channelType, $botId, $chatId, $event, $now);
        }

        $this->logger->info('Bot joined conversation', [
            'channel_type' => $channelType,
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'chat_title' => $event->chat->title,
        ]);
    }

    private function handleBotLeft(
        string $channelType,
        string $botId,
        string $chatId,
        \DateTimeImmutable $now,
    ): void {
        $sql = <<<'SQL'
            UPDATE telegram_chats
            SET left_at = :left_at
            WHERE bot_id = :bot_id AND chat_id = :chat_id AND channel_type = :channel_type
        SQL;

        $this->connection->executeStatement($sql, [
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'channel_type' => $channelType,
            'left_at' => $now,
        ], [
            'bot_id' => Types::STRING,
            'chat_id' => Types::STRING,
            'channel_type' => Types::STRING,
            'left_at' => Types::DATETIME_IMMUTABLE,
        ]);

        $this->logger->info('Bot left conversation', [
            'channel_type' => $channelType,
            'bot_id' => $botId,
            'chat_id' => $chatId,
        ]);
    }

    private function ensureConversationExists(
        string $channelType,
        string $botId,
        string $chatId,
        NormalizedEvent $event,
    ): void {
        $existing = $this->findByBotAndChatId($channelType, $botId, $chatId);

        if (!$existing) {
            $this->createConversation($channelType, $botId, $chatId, $event, null);

            $this->logger->info('New conversation tracked', [
                'channel_type' => $channelType,
                'bot_id' => $botId,
                'chat_id' => $chatId,
                'chat_title' => $event->chat->title,
            ]);

            return;
        }

        // Update title if changed
        if (null !== $event->chat->title && $event->chat->title !== ($existing['title'] ?? '')) {
            $this->connection->update(
                'telegram_chats',
                ['title' => $event->chat->title],
                ['id' => $existing['id']],
                ['title' => Types::STRING],
            );
        }

        // Detect thread support
        if (null !== $event->chat->threadId && !($existing['has_threads'] ?? false)) {
            $this->connection->update(
                'telegram_chats',
                ['has_threads' => true],
                ['id' => $existing['id']],
                ['has_threads' => Types::BOOLEAN],
            );
        }
    }

    private function createConversation(
        string $channelType,
        string $botId,
        string $chatId,
        NormalizedEvent $event,
        ?\DateTimeImmutable $joinedAt,
    ): void {
        $id = $this->connection->executeQuery('SELECT gen_random_uuid()')->fetchOne();

        $this->connection->insert('telegram_chats', [
            'id' => $id,
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'title' => $event->chat->title,
            'type' => $event->chat->type,
            'has_threads' => null !== $event->chat->threadId,
            'joined_at' => $joinedAt,
            'channel_type' => $channelType,
        ], [
            'id' => Types::STRING,
            'bot_id' => Types::STRING,
            'chat_id' => Types::STRING,
            'title' => Types::STRING,
            'type' => Types::STRING,
            'has_threads' => Types::BOOLEAN,
            'joined_at' => Types::DATETIME_IMMUTABLE,
            'channel_type' => Types::STRING,
        ]);
    }

    private function updateLastMessageTime(
        string $channelType,
        string $botId,
        string $chatId,
        \DateTimeImmutable $time,
    ): void {
        $sql = <<<'SQL'
            UPDATE telegram_chats
            SET last_message_at = :time
            WHERE bot_id = :bot_id AND chat_id = :chat_id AND channel_type = :channel_type
        SQL;

        $this->connection->executeStatement($sql, [
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'channel_type' => $channelType,
            'time' => $time,
        ], [
            'bot_id' => Types::STRING,
            'chat_id' => Types::STRING,
            'channel_type' => Types::STRING,
            'time' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findByBotAndChatId(string $channelType, string $botId, string $chatId): ?array
    {
        $sql = <<<'SQL'
            SELECT * FROM telegram_chats
            WHERE bot_id = :bot_id AND chat_id = :chat_id AND channel_type = :channel_type
        SQL;

        $row = $this->connection->fetchAssociative($sql, [
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'channel_type' => $channelType,
        ]);

        if (!$row) {
            return null;
        }

        return $this->hydrateConversation($row);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateConversation(array $row): array
    {
        $conversation = $row;

        if (isset($conversation['metadata']) && is_string($conversation['metadata'])) {
            $conversation['metadata'] = json_decode($conversation['metadata'], true);
        }

        foreach (['joined_at', 'left_at', 'last_message_at', 'created_at', 'updated_at'] as $field) {
            if (isset($conversation[$field]) && is_string($conversation[$field])) {
                $conversation[$field] = new \DateTimeImmutable($conversation[$field]);
            }
        }

        return $conversation;
    }
}
