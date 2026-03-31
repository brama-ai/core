<?php

declare(strict_types=1);

namespace App\Channel\DTO;

/**
 * Represents the delivery destination for a channel message.
 *
 * The address field supports two formats:
 *   - "chat_id"           — send to the chat root
 *   - "chat_id:thread_id" — send to a specific forum thread inside the chat
 */
readonly class DeliveryTarget
{
    public function __construct(
        /** Raw address string: "chat_id" or "chat_id:thread_id" */
        public string $address,
        /** Resolved chat_id */
        public string $chatId = '',
        /** Resolved thread_id, null when not targeting a thread */
        public ?int $threadId = null,
    ) {
    }

    /**
     * Parse address into a DeliveryTarget with resolved chatId and threadId.
     */
    public static function fromAddress(string $address): self
    {
        if (str_contains($address, ':')) {
            [$chatId, $threadId] = explode(':', $address, 2);

            return new self(
                address: $address,
                chatId: $chatId,
                threadId: (int) $threadId,
            );
        }

        return new self(
            address: $address,
            chatId: $address,
            threadId: null,
        );
    }
}
