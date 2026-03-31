<?php

declare(strict_types=1);

namespace App\Channel\DTO;

class NormalizedMessage
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $text = null,
        public readonly ?string $replyToMessageId = null,
        public readonly bool $hasMedia = false,
        public readonly ?string $mediaType = null,
        public readonly ?string $forwardFrom = null,
        public readonly ?string $timestamp = null,
        public readonly ?string $commandName = null,
        public readonly ?string $commandArgs = null,
        public readonly ?string $callbackData = null,
        public readonly ?string $callbackQueryId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'reply_to_message_id' => $this->replyToMessageId,
            'has_media' => $this->hasMedia,
            'media_type' => $this->mediaType,
            'forward_from' => $this->forwardFrom,
            'timestamp' => $this->timestamp,
            'command_name' => $this->commandName,
            'command_args' => $this->commandArgs,
            'callback_data' => $this->callbackData,
        ];
    }
}
