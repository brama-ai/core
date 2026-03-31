<?php

declare(strict_types=1);

namespace App\Telegram\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

class TelegramChatRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $id = $this->connection->executeQuery('SELECT gen_random_uuid()')->fetchOne();

        $this->connection->insert('channel_conversations', [
            'id' => $id,
            'bot_id' => $data['bot_id'],
            'chat_id' => $data['chat_id'],
            'title' => $data['title'] ?? null,
            'type' => $data['type'],
            'has_threads' => $data['has_threads'] ?? false,
            'member_count' => $data['member_count'] ?? null,
            'joined_at' => $data['joined_at'] ?? null,
            'left_at' => $data['left_at'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'last_message_at' => $data['last_message_at'] ?? null,
        ], [
            'id' => Types::STRING,
            'bot_id' => Types::STRING,
            'chat_id' => Types::BIGINT,
            'title' => Types::STRING,
            'type' => Types::STRING,
            'has_threads' => Types::BOOLEAN,
            'member_count' => Types::INTEGER,
            'joined_at' => Types::DATETIME_IMMUTABLE,
            'left_at' => Types::DATETIME_IMMUTABLE,
            'metadata' => Types::JSON,
            'last_message_at' => Types::DATETIME_IMMUTABLE,
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT * FROM channel_conversations WHERE id = :id';
        $chat = $this->connection->fetchAssociative($sql, ['id' => $id]);

        if (!$chat) {
            return null;
        }

        return $this->hydrateChat($chat);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByBotAndChatId(string $botId, int $chatId): ?array
    {
        $sql = 'SELECT * FROM channel_conversations WHERE bot_id = :bot_id AND chat_id = :chat_id';
        $chat = $this->connection->fetchAssociative($sql, [
            'bot_id' => $botId,
            'chat_id' => $chatId,
        ]);

        if (!$chat) {
            return null;
        }

        return $this->hydrateChat($chat);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = <<<'SQL'
            SELECT cc.*, ci.channel_username AS bot_username
            FROM channel_conversations cc
            LEFT JOIN channel_instances ci ON cc.bot_id = ci.id
            ORDER BY cc.last_message_at DESC NULLS LAST, cc.joined_at DESC NULLS LAST
        SQL;

        $chats = $this->connection->fetchAllAssociative($sql);

        return array_map([$this, 'hydrateChat'], $chats);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findActiveByBot(string $botId): array
    {
        $sql = <<<'SQL'
            SELECT * FROM channel_conversations
            WHERE bot_id = :bot_id AND left_at IS NULL
            ORDER BY last_message_at DESC NULLS LAST
        SQL;

        $chats = $this->connection->fetchAllAssociative($sql, ['bot_id' => $botId]);

        return array_map([$this, 'hydrateChat'], $chats);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsert(array $data): string
    {
        $existing = $this->findByBotAndChatId($data['bot_id'], $data['chat_id']);

        if ($existing) {
            $this->update($existing['id'], $data);

            return $existing['id'];
        }

        return $this->create($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): bool
    {
        $updateData = [];
        $updateTypes = [];

        // Map updateable fields
        $fields = [
            'title' => Types::STRING,
            'type' => Types::STRING,
            'has_threads' => Types::BOOLEAN,
            'member_count' => Types::INTEGER,
            'joined_at' => Types::DATETIME_IMMUTABLE,
            'left_at' => Types::DATETIME_IMMUTABLE,
            'last_message_at' => Types::DATETIME_IMMUTABLE,
        ];

        foreach ($fields as $field => $type) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
                $updateTypes[$field] = $type;
            }
        }

        // Handle JSON metadata field
        if (isset($data['metadata'])) {
            $updateData['metadata'] = json_encode($data['metadata']);
            $updateTypes['metadata'] = Types::JSON;
        }

        if (empty($updateData)) {
            return false;
        }

        $affected = $this->connection->update(
            'channel_conversations',
            $updateData,
            ['id' => $id],
            $updateTypes
        );

        return $affected > 0;
    }

    public function updateLastMessageTime(string $botId, int $chatId, \DateTimeImmutable $time): void
    {
        $sql = <<<'SQL'
            UPDATE channel_conversations
            SET last_message_at = :time
            WHERE bot_id = :bot_id AND chat_id = :chat_id
        SQL;

        $this->connection->executeStatement($sql, [
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'time' => $time,
        ], [
            'bot_id' => Types::STRING,
            'chat_id' => Types::BIGINT,
            'time' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    public function markJoined(string $botId, int $chatId, \DateTimeImmutable $joinedAt): void
    {
        $sql = <<<'SQL'
            UPDATE channel_conversations
            SET joined_at = :joined_at, left_at = NULL
            WHERE bot_id = :bot_id AND chat_id = :chat_id
        SQL;

        $this->connection->executeStatement($sql, [
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'joined_at' => $joinedAt,
        ], [
            'bot_id' => Types::STRING,
            'chat_id' => Types::BIGINT,
            'joined_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    public function markLeft(string $botId, int $chatId, \DateTimeImmutable $leftAt): void
    {
        $sql = <<<'SQL'
            UPDATE channel_conversations
            SET left_at = :left_at
            WHERE bot_id = :bot_id AND chat_id = :chat_id
        SQL;

        $this->connection->executeStatement($sql, [
            'bot_id' => $botId,
            'chat_id' => $chatId,
            'left_at' => $leftAt,
        ], [
            'bot_id' => Types::STRING,
            'chat_id' => Types::BIGINT,
            'left_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getActivityStats(string $botId, int $days = 7): array
    {
        $sql = <<<'SQL'
            SELECT
                COUNT(*) as total_chats,
                COUNT(CASE WHEN left_at IS NULL THEN 1 END) as active_chats,
                COUNT(CASE WHEN last_message_at > :since THEN 1 END) as recently_active,
                SUM(member_count) as total_members
            FROM channel_conversations
            WHERE bot_id = :bot_id
        SQL;

        $since = (new \DateTimeImmutable())->modify("-{$days} days");

        return $this->connection->fetchAssociative($sql, [
            'bot_id' => $botId,
            'since' => $since,
        ], [
            'bot_id' => Types::STRING,
            'since' => Types::DATETIME_IMMUTABLE,
        ]) ?: [];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateChat(array $row): array
    {
        $chat = $row;

        // Decode JSON metadata field
        if (isset($chat['metadata']) && is_string($chat['metadata'])) {
            $chat['metadata'] = json_decode($chat['metadata'], true);
        }

        // Convert datetime strings to DateTimeImmutable objects if needed
        foreach (['joined_at', 'left_at', 'last_message_at', 'created_at', 'updated_at'] as $field) {
            if (isset($chat[$field]) && is_string($chat[$field])) {
                $chat[$field] = new \DateTimeImmutable($chat[$field]);
            }
        }

        return $chat;
    }
}
