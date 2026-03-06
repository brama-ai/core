<?php

declare(strict_types=1);

namespace App\A2A\DTO;

/**
 * Declared capabilities of an A2A agent.
 *
 * Describes what protocol features the agent supports, such as streaming
 * responses, push notifications, and state transition history.
 */
final readonly class AgentCapabilities
{
    public function __construct(
        /** @var bool Whether the agent supports streaming responses via SSE */
        public bool $streaming = false,
        /** @var bool Whether the agent supports push notification delivery */
        public bool $pushNotifications = false,
        /** @var bool Whether the agent exposes task state transition history */
        public bool $stateTransitionHistory = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            streaming: (bool) ($data['streaming'] ?? false),
            pushNotifications: (bool) ($data['pushNotifications'] ?? false),
            stateTransitionHistory: (bool) ($data['stateTransitionHistory'] ?? false),
        );
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'streaming' => $this->streaming,
            'pushNotifications' => $this->pushNotifications,
            'stateTransitionHistory' => $this->stateTransitionHistory,
        ];
    }
}
