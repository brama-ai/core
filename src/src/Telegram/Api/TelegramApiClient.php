<?php

declare(strict_types=1);

namespace App\Telegram\Api;

use Psr\Log\LoggerInterface;

final class TelegramApiClient implements TelegramApiClientInterface
{
    private const API_BASE = 'https://api.telegram.org/bot';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function sendMessage(string $botToken, array $params): array
    {
        return $this->call($botToken, 'sendMessage', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function editMessageText(string $botToken, array $params): array
    {
        return $this->call($botToken, 'editMessageText', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function editMessageReplyMarkup(string $botToken, array $params): array
    {
        return $this->call($botToken, 'editMessageReplyMarkup', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function deleteMessage(string $botToken, array $params): array
    {
        return $this->call($botToken, 'deleteMessage', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function sendPhoto(string $botToken, array $params): array
    {
        return $this->call($botToken, 'sendPhoto', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function sendMediaGroup(string $botToken, array $params): array
    {
        return $this->call($botToken, 'sendMediaGroup', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function copyMessage(string $botToken, array $params): array
    {
        return $this->call($botToken, 'copyMessage', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function answerCallbackQuery(string $botToken, array $params): array
    {
        return $this->call($botToken, 'answerCallbackQuery', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function getMe(string $botToken): array
    {
        return $this->call($botToken, 'getMe', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getChatMember(string $botToken, string $chatId, string $userId): array
    {
        return $this->call($botToken, 'getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getChatMemberCount(string $botToken, string $chatId): array
    {
        return $this->call($botToken, 'getChatMemberCount', [
            'chat_id' => $chatId,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function setWebhook(string $botToken, array $params): array
    {
        return $this->call($botToken, 'setWebhook', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteWebhook(string $botToken): array
    {
        return $this->call($botToken, 'deleteWebhook', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function getWebhookInfo(string $botToken): array
    {
        return $this->call($botToken, 'getWebhookInfo', []);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function getUpdates(string $botToken, array $params = []): array
    {
        return $this->call($botToken, 'getUpdates', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function pinChatMessage(string $botToken, array $params): array
    {
        return $this->call($botToken, 'pinChatMessage', $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function call(string $botToken, string $method, array $params): array
    {
        $url = self::API_BASE.$botToken.'/'.$method;
        $body = json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: ".strlen($body)."\r\n",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static fn (): bool => true);

        try {
            $result = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if (false === $result) {
            $this->logger->error('Telegram API call failed: connection error', [
                'method' => $method,
            ]);

            return ['ok' => false, 'description' => 'Connection failed'];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

        if (!($decoded['ok'] ?? false)) {
            $this->logger->warning('Telegram API returned error', [
                'method' => $method,
                'error_code' => $decoded['error_code'] ?? 0,
                'description' => $decoded['description'] ?? 'unknown',
            ]);
        }

        return $decoded;
    }
}
