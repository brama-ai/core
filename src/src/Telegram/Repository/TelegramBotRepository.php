<?php

declare(strict_types=1);

namespace App\Telegram\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

class TelegramBotRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $encryptionKey,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $id = $this->connection->executeQuery('SELECT gen_random_uuid()')->fetchOne();

        $this->connection->insert('channel_instances', [
            'id' => $id,
            'channel_username' => $data['bot_username'],
            'credential_encrypted' => $this->encryptToken($data['bot_token']),
            'webhook_secret' => $data['webhook_secret'] ?? null,
            'community_id' => $data['community_id'] ?? null,
            'privacy_mode' => $data['privacy_mode'] ?? 'enabled',
            'polling_mode' => $data['polling_mode'] ?? false,
            'role_overrides' => isset($data['role_overrides']) ? json_encode($data['role_overrides']) : null,
            'config' => isset($data['config']) ? json_encode($data['config']) : null,
            'enabled' => $data['enabled'] ?? true,
            'webhook_url' => $data['webhook_url'] ?? null,
        ], [
            'id' => Types::STRING,
            'channel_username' => Types::STRING,
            'credential_encrypted' => Types::TEXT,
            'webhook_secret' => Types::STRING,
            'community_id' => Types::STRING,
            'privacy_mode' => Types::STRING,
            'polling_mode' => Types::BOOLEAN,
            'role_overrides' => Types::JSON,
            'config' => Types::JSON,
            'enabled' => Types::BOOLEAN,
            'webhook_url' => Types::STRING,
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $id): ?array
    {
        $sql = 'SELECT * FROM channel_instances WHERE id = :id';
        $bot = $this->connection->fetchAssociative($sql, ['id' => $id]);

        if (!$bot) {
            return null;
        }

        return $this->hydrateBot($bot);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $sql = 'SELECT * FROM channel_instances WHERE channel_username = :username';
        $bot = $this->connection->fetchAssociative($sql, ['username' => $username]);

        if (!$bot) {
            return null;
        }

        return $this->hydrateBot($bot);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $sql = 'SELECT * FROM channel_instances ORDER BY created_at DESC';
        $bots = $this->connection->fetchAllAssociative($sql);

        return array_map([$this, 'hydrateBot'], $bots);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findEnabled(): array
    {
        $sql = 'SELECT * FROM channel_instances WHERE enabled = true ORDER BY created_at DESC';
        $bots = $this->connection->fetchAllAssociative($sql);

        return array_map([$this, 'hydrateBot'], $bots);
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
            'channel_username' => Types::STRING,
            'webhook_secret' => Types::STRING,
            'community_id' => Types::STRING,
            'privacy_mode' => Types::STRING,
            'polling_mode' => Types::BOOLEAN,
            'enabled' => Types::BOOLEAN,
            'webhook_url' => Types::STRING,
            'last_update_id' => Types::BIGINT,
        ];

        foreach ($fields as $field => $type) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
                $updateTypes[$field] = $type;
            }
        }

        // Support legacy key 'bot_username' as alias for 'channel_username'
        if (!isset($updateData['channel_username']) && array_key_exists('bot_username', $data)) {
            $updateData['channel_username'] = $data['bot_username'];
            $updateTypes['channel_username'] = Types::STRING;
        }

        // Handle encrypted token
        if (isset($data['bot_token'])) {
            $updateData['credential_encrypted'] = $this->encryptToken($data['bot_token']);
            $updateTypes['credential_encrypted'] = Types::TEXT;
        }

        // Handle JSON fields
        if (isset($data['role_overrides'])) {
            $updateData['role_overrides'] = json_encode($data['role_overrides']);
            $updateTypes['role_overrides'] = Types::JSON;
        }

        if (isset($data['config'])) {
            $updateData['config'] = json_encode($data['config']);
            $updateTypes['config'] = Types::JSON;
        }

        if (empty($updateData)) {
            return false;
        }

        $affected = $this->connection->update(
            'channel_instances',
            $updateData,
            ['id' => $id],
            $updateTypes
        );

        return $affected > 0;
    }

    public function delete(string $id): bool
    {
        $affected = $this->connection->delete('channel_instances', ['id' => $id]);

        return $affected > 0;
    }

    public function updateLastUpdateId(string $id, int $updateId): void
    {
        $this->connection->update(
            'channel_instances',
            ['last_update_id' => $updateId],
            ['id' => $id],
            ['last_update_id' => Types::BIGINT]
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function hydrateBot(array $row): array
    {
        $bot = $row;

        // Decrypt token — column is now credential_encrypted
        if (isset($bot['credential_encrypted'])) {
            $bot['bot_token'] = $this->decryptToken($bot['credential_encrypted']);
            unset($bot['credential_encrypted']);
        }

        // Expose channel_username also as bot_username for backward compatibility
        if (isset($bot['channel_username'])) {
            $bot['bot_username'] = $bot['channel_username'];
        }

        // Decode JSON fields
        if (isset($bot['role_overrides']) && is_string($bot['role_overrides'])) {
            $bot['role_overrides'] = json_decode($bot['role_overrides'], true);
        }

        if (isset($bot['config']) && is_string($bot['config'])) {
            $bot['config'] = json_decode($bot['config'], true);
        }

        return $bot;
    }

    private function encryptToken(string $token): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = sodium_crypto_generichash($this->encryptionKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $encrypted = sodium_crypto_secretbox($token, $nonce, $key);

        return base64_encode($nonce.$encrypted);
    }

    private function decryptToken(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if (false === $decoded) {
            throw new \RuntimeException('Failed to decode bot token: invalid base64');
        }
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $key = sodium_crypto_generichash($this->encryptionKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if (false === $decrypted) {
            throw new \RuntimeException('Failed to decrypt bot token');
        }

        return $decrypted;
    }
}
