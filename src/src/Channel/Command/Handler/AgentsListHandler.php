<?php

declare(strict_types=1);

namespace App\Channel\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\DTO\DeliveryPayload;
use App\Channel\DTO\DeliveryTarget;
use App\Channel\DTO\NormalizedEvent;

final class AgentsListHandler
{
    public function __construct(
        private readonly AgentRegistryInterface $agentRegistry,
    ) {
    }

    public function handle(NormalizedEvent $event): DeliveryPayload
    {
        $agents = $this->agentRegistry->findAll();
        $address = null !== $event->chat->threadId
            ? $event->chat->id.':'.$event->chat->threadId
            : $event->chat->id;

        if ([] === $agents) {
            return new DeliveryPayload(
                botId: $event->botId,
                target: DeliveryTarget::fromAddress($address),
                text: 'Агентів не зареєстровано.',
            );
        }

        $lines = ["Зареєстровані агенти:\n"];

        foreach ($agents as $agent) {
            $enabled = (bool) ($agent['enabled'] ?? false);
            $status = $enabled ? '🟢' : '🔴';
            $name = (string) ($agent['name'] ?? 'unknown');

            /** @var array<string, mixed> $manifest */
            $manifest = is_string($agent['manifest'] ?? null)
                ? json_decode((string) $agent['manifest'], true, 512, JSON_THROW_ON_ERROR)
                : ($agent['manifest'] ?? []);

            $description = (string) ($manifest['description'] ?? '');
            $line = sprintf('%s %s', $status, $name);
            if ('' !== $description) {
                $line .= sprintf(' — %s', $description);
            }

            $lines[] = $line;
        }

        return new DeliveryPayload(
            botId: $event->botId,
            target: DeliveryTarget::fromAddress($address),
            text: implode("\n", $lines),
        );
    }
}
