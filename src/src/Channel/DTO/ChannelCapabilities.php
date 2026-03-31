<?php

declare(strict_types=1);

namespace App\Channel\DTO;

/**
 * Declares what features a channel supports.
 */
final class ChannelCapabilities
{
    /**
     * @param list<string> $supportedParseFormats e.g. ['markdown', 'html', 'text']
     */
    public function __construct(
        public readonly bool $supportsThreads,
        public readonly bool $supportsReactions,
        public readonly bool $supportsEditing,
        public readonly bool $supportsMedia,
        public readonly bool $supportsMediaGroups,
        public readonly bool $supportsCallbackQueries,
        public readonly int $maxMessageLength,
        public readonly int $maxCaptionLength,
        public readonly array $supportedParseFormats,
    ) {
    }
}
