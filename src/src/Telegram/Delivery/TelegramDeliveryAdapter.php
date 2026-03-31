<?php

declare(strict_types=1);

namespace App\Telegram\Delivery;

use App\Channel\DTO\DeliveryPayload;
use App\Channel\DTO\DeliveryResult;
use App\Channel\DTO\DeliveryTarget;
use App\Telegram\Service\TelegramSenderInterface;
use Psr\Log\LoggerInterface;

/**
 * Delivery channel adapter for Telegram.
 *
 * Maps a generic DeliveryPayload to a TelegramSender::send() call and
 * returns a DeliveryResult carrying the Telegram message_id on success.
 *
 * Address format: "chat_id" or "chat_id:thread_id"
 *
 * Content-type → parse_mode mapping:
 *   - "markdown" → MarkdownV2
 *   - "card"     → HTML
 *   - "text"     → (no parse_mode, plain text)
 */
final class TelegramDeliveryAdapter implements ChannelAdapterInterface
{
    public function __construct(
        private readonly TelegramSenderInterface $sender,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(DeliveryPayload $payload): DeliveryResult
    {
        $target = DeliveryTarget::fromAddress($payload->target->address);

        $options = $this->buildOptions($payload->contentType, $target->threadId);

        $this->logger->info('Sending Telegram delivery', [
            'bot_id' => $payload->botId,
            'chat_id' => $target->chatId,
            'thread_id' => $target->threadId,
            'content_type' => $payload->contentType,
        ]);

        try {
            $response = $this->sender->send(
                $payload->botId,
                $target->chatId,
                $payload->text,
                $options,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Telegram delivery exception', [
                'bot_id' => $payload->botId,
                'chat_id' => $target->chatId,
                'exception' => $e->getMessage(),
            ]);

            return DeliveryResult::failure($e->getMessage());
        }

        if (!($response['ok'] ?? false)) {
            $error = (string) ($response['description'] ?? 'Unknown error');

            $this->logger->error('Telegram delivery failed', [
                'bot_id' => $payload->botId,
                'chat_id' => $target->chatId,
                'error' => $error,
            ]);

            return DeliveryResult::failure($error);
        }

        $messageId = (string) ($response['result']['message_id'] ?? '');

        return DeliveryResult::success($messageId);
    }

    public function supports(string $type): bool
    {
        return 'telegram' === $type;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(string $contentType, ?int $threadId): array
    {
        $options = [];

        $parseMode = $this->resolveParseMode($contentType);
        if (null !== $parseMode) {
            $options['parse_mode'] = $parseMode;
        }

        if (null !== $threadId) {
            $options['thread_id'] = $threadId;
        }

        return $options;
    }

    private function resolveParseMode(string $contentType): ?string
    {
        return match ($contentType) {
            'markdown' => 'MarkdownV2',
            'card' => 'HTML',
            default => null,
        };
    }
}
