<?php

declare(strict_types=1);

namespace App\Telegram\Service;

use App\Telegram\Api\TelegramApiClientInterface;

final class TelegramRoleResolver implements TelegramRoleResolverInterface
{
    public function __construct(
        private readonly TelegramApiClientInterface $apiClient,
        private readonly TelegramBotRegistry $botRegistry,
    ) {
    }

    /**
     * Resolve platform role for a Telegram user in a given chat.
     * Checks role_overrides first, then Telegram chat member status.
     */
    public function resolve(string $botId, string $chatId, string $userId): string
    {
        $bot = $this->botRegistry->getBot($botId);
        if (!$bot) {
            return 'user';
        }

        // Check role overrides
        $overrides = $bot['role_overrides'] ?? [];
        if (is_array($overrides) && isset($overrides[$userId])) {
            return (string) $overrides[$userId];
        }

        // Query Telegram for chat member status
        $token = (string) $bot['bot_token'];
        $result = $this->apiClient->getChatMember($token, $chatId, $userId);

        if (!($result['ok'] ?? false)) {
            return 'user';
        }

        $status = (string) ($result['result']['status'] ?? 'member');

        return $this->mapTelegramStatus($status);
    }

    public function mapTelegramStatus(string $status): string
    {
        return match ($status) {
            'creator' => 'admin',
            'administrator' => 'moderator',
            'member' => 'user',
            'restricted' => 'user',
            default => 'user',
        };
    }
}
