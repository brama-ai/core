<?php

declare(strict_types=1);

namespace App\Channel\DTO;

class NormalizedChat
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $title = null,
        public readonly ?string $threadId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'thread_id' => $this->threadId,
        ];
    }
}
