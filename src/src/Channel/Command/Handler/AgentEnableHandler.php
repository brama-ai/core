<?php

declare(strict_types=1);

namespace App\Channel\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\DTO\DeliveryPayload;
use App\Channel\DTO\DeliveryTarget;
use App\Channel\DTO\NormalizedEvent;

final class AgentEnableHandler
{
    public function __construct(
        private readonly AgentRegistryInterface $agentRegistry,
    ) {
    }

    public function handle(NormalizedEvent $event, string $agentName, string $role): DeliveryPayload
    {
        $address = null !== $event->chat->threadId
            ? $event->chat->id.':'.$event->chat->threadId
            : $event->chat->id;

        $agent = $this->agentRegistry->findByName($agentName);

        if (null === $agent) {
            return new DeliveryPayload(
                botId: $event->botId,
                target: DeliveryTarget::fromAddress($address),
                text: sprintf('Агент "%s" не знайдений. Використовуйте /agents для списку.', $agentName),
            );
        }

        if ($agent['enabled'] ?? false) {
            return new DeliveryPayload(
                botId: $event->botId,
                target: DeliveryTarget::fromAddress($address),
                text: sprintf('Агент %s вже увімкнений.', $agentName),
            );
        }

        $enabledBy = $event->sender->username ?? $event->sender->id;
        $result = $this->agentRegistry->enable($agentName, $enabledBy);

        $text = $result
            ? sprintf('Агент %s увімкнений.', $agentName)
            : sprintf('Не вдалося увімкнути агента %s.', $agentName);

        return new DeliveryPayload(
            botId: $event->botId,
            target: DeliveryTarget::fromAddress($address),
            text: $text,
        );
    }
}
