<?php

declare(strict_types=1);

namespace App\Channel\DTO;

/**
 * Carries the message content and routing information for a delivery request.
 *
 * Supported content_type values:
 *   - "text"     — plain text, no parse mode
 *   - "markdown" — channel-specific markdown parse mode
 *   - "card"     — HTML-formatted rich content
 */
readonly class DeliveryPayload
{
    public function __construct(
        /** Channel instance identifier used to look up credentials */
        public string $botId,
        /** Delivery destination */
        public DeliveryTarget $target,
        /** Message body */
        public string $text,
        /**
         * Content type hint: "text" | "markdown" | "card"
         * Controls the parse_mode sent to the channel API.
         */
        public string $contentType = 'text',
    ) {
    }
}
