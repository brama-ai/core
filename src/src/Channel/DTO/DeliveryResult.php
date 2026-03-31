<?php

declare(strict_types=1);

namespace App\Channel\DTO;

/**
 * Represents the outcome of a delivery attempt.
 */
readonly class DeliveryResult
{
    public function __construct(
        /** Whether the delivery succeeded */
        public bool $success,
        /** External message_id returned by the channel API on success */
        public ?string $externalMessageId = null,
        /** Human-readable error description on failure */
        public ?string $errorMessage = null,
    ) {
    }

    public static function success(string $externalMessageId): self
    {
        return new self(success: true, externalMessageId: $externalMessageId);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(success: false, errorMessage: $errorMessage);
    }
}
