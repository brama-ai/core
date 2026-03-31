<?php

declare(strict_types=1);

namespace App\Channel;

use Doctrine\DBAL\Connection;

/**
 * Generic encrypted credential storage for channel instances.
 *
 * Extracts the encryption/decryption logic from TelegramBotRepository
 * and makes it channel-agnostic. Credentials are stored encrypted in
 * the channel_instances table (credential_encrypted column) keyed by instance ID.
 *
 * Uses CHANNEL_ENCRYPTION_KEY env var with fallback to TELEGRAM_ENCRYPTION_KEY.
 */
final class ChannelCredentialVault
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $encryptionKey,
    ) {
    }

    /**
     * Encrypt a plain-text credential and return the encrypted string.
     */
    public function encrypt(string $plainCredential): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $key = $this->deriveKey();
        $encrypted = sodium_crypto_secretbox($plainCredential, $nonce, $key);

        return base64_encode($nonce.$encrypted);
    }

    /**
     * Decrypt the stored credential for the given channel instance ID.
     *
     * @throws \RuntimeException when the instance is not found or decryption fails
     */
    public function decrypt(string $channelInstanceId): string
    {
        $encrypted = $this->fetchEncryptedCredential($channelInstanceId);

        return $this->decryptRaw($encrypted);
    }

    /**
     * Return the encrypted credential string (a reference) for the given channel instance.
     * Used when passing a credential reference to a channel agent (Option A: pass decrypted token).
     *
     * @throws \RuntimeException when the instance is not found
     */
    public function getCredentialRef(string $channelInstanceId): string
    {
        // Option A from design.md: pass decrypted token in A2A call (local network only)
        return $this->decrypt($channelInstanceId);
    }

    /**
     * Decrypt a raw encrypted string (without DB lookup).
     * Useful for TelegramBotRepository backward compatibility.
     *
     * @throws \RuntimeException on decryption failure
     */
    public function decryptRaw(string $encrypted): string
    {
        $decoded = base64_decode($encrypted, true);
        if (false === $decoded) {
            throw new \RuntimeException('Failed to decode credential: invalid base64');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $key = $this->deriveKey();
        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if (false === $decrypted) {
            throw new \RuntimeException('Failed to decrypt credential');
        }

        return $decrypted;
    }

    private function fetchEncryptedCredential(string $channelInstanceId): string
    {
        $sql = 'SELECT credential_encrypted FROM channel_instances WHERE id = :id';
        $row = $this->connection->fetchAssociative($sql, ['id' => $channelInstanceId]);

        if (!$row || empty($row['credential_encrypted'])) {
            throw new \RuntimeException(sprintf('No credential found for channel instance "%s"', $channelInstanceId));
        }

        return (string) $row['credential_encrypted'];
    }

    private function deriveKey(): string
    {
        return sodium_crypto_generichash($this->encryptionKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
