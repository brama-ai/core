<?php

declare(strict_types=1);

namespace App\Telegram\Service;

interface TelegramSenderInterface
{
    /**
     * @param array<string, mixed> $options Keys: thread_id, reply_to_message_id, parse_mode, reply_markup
     *
     * @return array<string, mixed> Telegram API response
     */
    public function send(string $botId, string $chatId, string $text, array $options = []): array;

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function sendPhoto(string $botId, string $chatId, string $photo, ?string $caption = null, array $options = []): array;

    /**
     * @param list<array<string, mixed>> $media   Array of InputMedia objects
     * @param array<string, mixed>       $options
     *
     * @return array<string, mixed>
     */
    public function sendMediaGroup(string $botId, string $chatId, array $media, array $options = []): array;

    /**
     * @return array<string, mixed>
     */
    public function answerCallbackQuery(string $botId, string $callbackQueryId, ?string $text = null, bool $showAlert = false): array;

    /**
     * @param array<string, mixed> $replyMarkup
     *
     * @return array<string, mixed>
     */
    public function editMessageReplyMarkup(string $botId, string $chatId, int $messageId, array $replyMarkup): array;
}
