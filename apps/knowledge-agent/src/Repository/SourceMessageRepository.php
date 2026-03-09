<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class SourceMessageRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsert(array $payload, string $requestId, ?string $traceId = null): string
    {
        /** @var array<string, mixed> $message */
        $message = isset($payload['message']) && \is_array($payload['message'])
            ? $payload['message']
            : $payload;

        /** @var array<string, mixed> $chat */
        $chat = isset($message['chat']) && \is_array($message['chat']) ? $message['chat'] : [];

        /** @var array<string, mixed> $sender */
        $sender = isset($message['sender']) && \is_array($message['sender']) ? $message['sender'] : [];
        if ([] === $sender && isset($message['author']) && \is_array($message['author'])) {
            /** @var array<string, mixed> $author */
            $author = $message['author'];
            $sender = $author;
        }

        /** @var array<string, mixed> $metadata */
        $metadata = isset($payload['metadata']) && \is_array($payload['metadata'])
            ? $payload['metadata']
            : (isset($payload['meta']) && \is_array($payload['meta']) ? $payload['meta'] : []);

        $sourcePlatform = $this->stringOrDefault(
            $message['source_platform'] ?? $message['platform'] ?? $metadata['platform'] ?? null,
            'telegram',
        );
        $eventType = $this->stringOrDefault($message['event_type'] ?? $metadata['event_type'] ?? null, 'message_created');

        $chatId = $this->nullableString($message['chat_id'] ?? $chat['id'] ?? $metadata['chat_id'] ?? null);
        $chatTitle = $this->nullableString($message['chat_title'] ?? $chat['title'] ?? null);
        $chatType = $this->nullableString($message['chat_type'] ?? $chat['type'] ?? null);
        $channel = $this->nullableString($message['channel'] ?? $metadata['channel'] ?? null);
        $messageId = $this->nullableString($message['message_id'] ?? $message['id'] ?? $metadata['message_id'] ?? null);
        $threadId = $this->nullableString($message['thread_id'] ?? $message['message_thread_id'] ?? null);
        $senderId = $this->nullableString($message['sender_id'] ?? $sender['id'] ?? null);
        $senderUsername = $this->nullableString($message['sender_username'] ?? $sender['username'] ?? null);
        $senderDisplayName = $this->nullableString(
            $message['sender_display_name'] ?? $sender['display_name'] ?? $sender['name'] ?? null,
        );
        $messageText = $this->nullableString($message['text'] ?? $message['message'] ?? null);
        $messageTimestamp = $this->normalizeTimestamp(
            $message['timestamp'] ?? $message['sent_at'] ?? $message['date'] ?? $metadata['timestamp'] ?? null,
        );

        $rawPayload = json_encode($payload, \JSON_THROW_ON_ERROR);
        $metadataJson = json_encode($metadata, \JSON_THROW_ON_ERROR);

        $id = $this->connection->fetchOne(<<<'SQL'
            INSERT INTO knowledge_source_messages (
                source_platform,
                event_type,
                chat_id,
                chat_title,
                chat_type,
                channel,
                message_id,
                thread_id,
                sender_id,
                sender_username,
                sender_display_name,
                message_text,
                message_timestamp,
                trace_id,
                request_id,
                metadata,
                raw_payload,
                created_at
            ) VALUES (
                :source_platform,
                :event_type,
                :chat_id,
                :chat_title,
                :chat_type,
                :channel,
                :message_id,
                :thread_id,
                :sender_id,
                :sender_username,
                :sender_display_name,
                :message_text,
                :message_timestamp,
                :trace_id,
                :request_id,
                CAST(:metadata AS jsonb),
                CAST(:raw_payload AS jsonb),
                now()
            )
            ON CONFLICT (source_platform, chat_id, message_id)
            DO UPDATE SET
                event_type = EXCLUDED.event_type,
                chat_title = EXCLUDED.chat_title,
                chat_type = EXCLUDED.chat_type,
                channel = EXCLUDED.channel,
                thread_id = EXCLUDED.thread_id,
                sender_id = EXCLUDED.sender_id,
                sender_username = EXCLUDED.sender_username,
                sender_display_name = EXCLUDED.sender_display_name,
                message_text = EXCLUDED.message_text,
                message_timestamp = EXCLUDED.message_timestamp,
                trace_id = EXCLUDED.trace_id,
                request_id = EXCLUDED.request_id,
                metadata = EXCLUDED.metadata,
                raw_payload = EXCLUDED.raw_payload
            RETURNING id
        SQL, [
            'source_platform' => $sourcePlatform,
            'event_type' => $eventType,
            'chat_id' => $chatId,
            'chat_title' => $chatTitle,
            'chat_type' => $chatType,
            'channel' => $channel,
            'message_id' => $messageId,
            'thread_id' => $threadId,
            'sender_id' => $senderId,
            'sender_username' => $senderUsername,
            'sender_display_name' => $senderDisplayName,
            'message_text' => $messageText,
            'message_timestamp' => $messageTimestamp,
            'trace_id' => $traceId,
            'request_id' => $requestId,
            'metadata' => $metadataJson,
            'raw_payload' => $rawPayload,
        ]);

        return (string) $id;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        if (!\is_string($value) && !is_numeric($value)) {
            return $default;
        }

        $normalized = trim((string) $value);

        return '' === $normalized ? $default : $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!\is_string($value) && !is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            if (\is_int($value) || (\is_string($value) && ctype_digit($value))) {
                $date = (new \DateTimeImmutable('@'.(string) $value))->setTimezone(new \DateTimeZone('UTC'));
            } else {
                $date = new \DateTimeImmutable((string) $value);
            }

            return $date->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
    }
}
