<?php

declare(strict_types=1);

namespace App\Channel\DTO;

class NormalizedSender
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $username = null,
        public readonly ?string $firstName = null,
        public readonly string $role = 'user',
        public readonly bool $isBot = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->firstName,
            'role' => $this->role,
            'is_bot' => $this->isBot,
        ];
    }
}
