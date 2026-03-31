<?php

declare(strict_types=1);

namespace App\Telegram\Api;

interface TelegramApiClientInterface
{
    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function sendMessage(string $botToken, array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function editMessageText(string $botToken, array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function editMessageReplyMarkup(string $botToken, array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function deleteMessage(string $botToken, array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function sendPhoto(string $botToken, array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function sendMediaGroup(string $botToken, array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function copyMessage(string $botToken, array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function answerCallbackQuery(string $botToken, array $params): array;

    /**
     * @return array<string, mixed>
     */
    public function getMe(string $botToken): array;

    /**
     * @return array<string, mixed>
     */
    public function getChatMember(string $botToken, string $chatId, string $userId): array;

    /**
     * @return array<string, mixed>
     */
    public function getChatMemberCount(string $botToken, string $chatId): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function setWebhook(string $botToken, array $params): array;

    /**
     * @return array<string, mixed>
     */
    public function deleteWebhook(string $botToken): array;

    /**
     * @return array<string, mixed>
     */
    public function getWebhookInfo(string $botToken): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getUpdates(string $botToken, array $params = []): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function pinChatMessage(string $botToken, array $params): array;
}
