<?php

declare(strict_types=1);

namespace App\Channel\DTO;

class NormalizedEvent
{
    public function __construct(
        public readonly string $eventType,
        public readonly string $platform,
        public readonly string $botId,
        public readonly NormalizedChat $chat,
        public readonly NormalizedSender $sender,
        public readonly NormalizedMessage $message,
        public readonly string $traceId,
        public readonly string $requestId,
        public readonly int $rawUpdateId,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'platform' => $this->platform,
            'bot_id' => $this->botId,
            'chat' => $this->chat->toArray(),
            'sender' => $this->sender->toArray(),
            'message' => $this->message->toArray(),
            'trace_id' => $this->traceId,
            'request_id' => $this->requestId,
            'raw_update_id' => $this->rawUpdateId,
        ];
    }
}
