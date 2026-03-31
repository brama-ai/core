<?php

declare(strict_types=1);

namespace App\Telegram\Service;

use App\Telegram\Api\TelegramApiClientInterface;
use Psr\Log\LoggerInterface;

final class TelegramSender implements TelegramSenderInterface
{
    public function __construct(
        private readonly TelegramApiClientInterface $apiClient,
        private readonly TelegramBotRegistry $botRegistry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $options Keys: thread_id, reply_to_message_id, parse_mode, reply_markup
     *
     * @return array<string, mixed> Telegram API response
     */
    public function send(string $botId, string $chatId, string $text, array $options = []): array
    {
        $bot = $this->botRegistry->getBot($botId);
        if (!$bot) {
            $this->logger->error('Cannot send message: bot not found', ['bot_id' => $botId]);

            return ['ok' => false, 'description' => 'Bot not found'];
        }

        $token = (string) $bot['bot_token'];

        // Split long messages
        if (mb_strlen($text) > 4096) {
            return $this->sendSplit($token, $chatId, $text, $options);
        }

        $params = $this->buildSendParams($chatId, $text, $options);

        $result = $this->apiClient->sendMessage($token, $params);

        // Fallback to HTML if MarkdownV2 fails
        if (!($result['ok'] ?? false) && ($options['parse_mode'] ?? '') === 'MarkdownV2') {
            $this->logger->info('MarkdownV2 failed, falling back to HTML', [
                'bot_id' => $botId,
                'chat_id' => $chatId,
            ]);
            $options['parse_mode'] = 'HTML';
            $params = $this->buildSendParams($chatId, $text, $options);
            $result = $this->apiClient->sendMessage($token, $params);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function sendPhoto(string $botId, string $chatId, string $photo, ?string $caption = null, array $options = []): array
    {
        $bot = $this->botRegistry->getBot($botId);
        if (!$bot) {
            return ['ok' => false, 'description' => 'Bot not found'];
        }

        $params = [
            'chat_id' => $chatId,
            'photo' => $photo,
        ];

        if (null !== $caption) {
            // Photo caption limit is 1024 chars
            if (mb_strlen($caption) > 1024) {
                $params['caption'] = mb_substr($caption, 0, 1024);
                // Send remaining text as follow-up message
                $remaining = mb_substr($caption, 1024);
            } else {
                $params['caption'] = $caption;
            }
        }

        if (isset($options['thread_id'])) {
            $params['message_thread_id'] = $options['thread_id'];
        }
        if (isset($options['parse_mode'])) {
            $params['parse_mode'] = $options['parse_mode'];
        }
        if (isset($options['reply_markup'])) {
            $params['reply_markup'] = $options['reply_markup'];
        }

        $result = $this->apiClient->sendPhoto((string) $bot['bot_token'], $params);

        // Send remaining caption as follow-up text if needed
        if (isset($remaining) && ($result['ok'] ?? false)) {
            $this->send($botId, $chatId, $remaining, $options);
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $media   Array of InputMedia objects
     * @param array<string, mixed>       $options
     *
     * @return array<string, mixed>
     */
    public function sendMediaGroup(string $botId, string $chatId, array $media, array $options = []): array
    {
        $bot = $this->botRegistry->getBot($botId);
        if (!$bot) {
            return ['ok' => false, 'description' => 'Bot not found'];
        }

        $params = [
            'chat_id' => $chatId,
            'media' => $media,
        ];

        if (isset($options['thread_id'])) {
            $params['message_thread_id'] = $options['thread_id'];
        }

        return $this->apiClient->sendMediaGroup((string) $bot['bot_token'], $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function answerCallbackQuery(string $botId, string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $bot = $this->botRegistry->getBot($botId);
        if (!$bot) {
            return ['ok' => false, 'description' => 'Bot not found'];
        }

        $params = ['callback_query_id' => $callbackQueryId];
        if (null !== $text) {
            $params['text'] = $text;
        }
        if ($showAlert) {
            $params['show_alert'] = true;
        }

        return $this->apiClient->answerCallbackQuery((string) $bot['bot_token'], $params);
    }

    /**
     * @param array<string, mixed> $replyMarkup
     *
     * @return array<string, mixed>
     */
    public function editMessageReplyMarkup(string $botId, string $chatId, int $messageId, array $replyMarkup): array
    {
        $bot = $this->botRegistry->getBot($botId);
        if (!$bot) {
            return ['ok' => false, 'description' => 'Bot not found'];
        }

        return $this->apiClient->editMessageReplyMarkup((string) $bot['bot_token'], [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function sendSplit(string $token, string $chatId, string $text, array $options): array
    {
        $chunks = $this->splitText($text, 4096);
        $lastResult = ['ok' => false];

        foreach ($chunks as $chunk) {
            $params = $this->buildSendParams($chatId, $chunk, $options);
            $lastResult = $this->apiClient->sendMessage($token, $params);

            if (!($lastResult['ok'] ?? false)) {
                return $lastResult;
            }

            // Only first message gets reply_to_message_id
            unset($options['reply_to_message_id']);
        }

        return $lastResult;
    }

    /**
     * @return list<string>
     */
    private function splitText(string $text, int $maxLength): array
    {
        if (mb_strlen($text) <= $maxLength) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (mb_strlen($remaining) > $maxLength) {
            $chunk = mb_substr($remaining, 0, $maxLength);

            // Try to split at paragraph boundary
            $lastNewline = strrpos($chunk, "\n\n");
            if (false !== $lastNewline && $lastNewline > $maxLength / 2) {
                $chunk = mb_substr($remaining, 0, $lastNewline);
            } else {
                // Try sentence boundary
                $lastDot = strrpos($chunk, '. ');
                if (false !== $lastDot && $lastDot > $maxLength / 2) {
                    $chunk = mb_substr($remaining, 0, $lastDot + 1);
                }
            }

            $chunks[] = $chunk;
            $remaining = ltrim(mb_substr($remaining, mb_strlen($chunk)));
        }

        if ('' !== $remaining) {
            $chunks[] = $remaining;
        }

        return $chunks;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function buildSendParams(string $chatId, string $text, array $options): array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if (isset($options['thread_id'])) {
            $params['message_thread_id'] = $options['thread_id'];
        }

        if (isset($options['reply_to_message_id'])) {
            $params['reply_parameters'] = ['message_id' => (int) $options['reply_to_message_id']];
        }

        if (isset($options['parse_mode'])) {
            $params['parse_mode'] = $options['parse_mode'];
        }

        if (isset($options['reply_markup'])) {
            $params['reply_markup'] = $options['reply_markup'];
        }

        return $params;
    }
}
