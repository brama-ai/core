<?php

declare(strict_types=1);

namespace App\Telegram\Service;

interface TelegramRoleResolverInterface
{
    /**
     * Resolve platform role for a Telegram user in a given chat.
     * Checks role_overrides first, then Telegram chat member status.
     */
    public function resolve(string $botId, string $chatId, string $userId): string;

    public function mapTelegramStatus(string $status): string;
}
