<?php

declare(strict_types=1);

namespace App\Channel\Command;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\ChannelManagerInterface;
use App\Channel\Command\Handler\AgentDisableHandler;
use App\Channel\Command\Handler\AgentEnableHandler;
use App\Channel\Command\Handler\AgentsListHandler;
use App\Channel\Command\Handler\HelpHandler;
use App\Channel\DTO\DeliveryPayload;
use App\Channel\DTO\DeliveryTarget;
use App\Channel\DTO\NormalizedEvent;
use Psr\Log\LoggerInterface;

final class PlatformCommandRouter
{
    /** @var array<string, array{handler: string, min_role: string}> */
    private const PLATFORM_COMMANDS = [
        '/help' => ['handler' => 'help', 'min_role' => 'user'],
        '/agents' => ['handler' => 'agents', 'min_role' => 'user'],
        '/agent' => ['handler' => 'agent', 'min_role' => 'moderator'],
    ];

    private const ROLE_HIERARCHY = ['admin' => 3, 'moderator' => 2, 'user' => 1];

    public function __construct(
        private readonly ChannelManagerInterface $channelManager,
        private readonly AgentRegistryInterface $agentRegistry,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function route(NormalizedEvent $event): void
    {
        $commandName = $event->message->commandName;
        $commandArgs = $event->message->commandArgs;

        if (null === $commandName) {
            return;
        }

        $this->logger->info('Routing platform command', [
            'command' => $commandName,
            'args' => $commandArgs,
            'sender' => $event->sender->id,
            'chat_id' => $event->chat->id,
            'platform' => $event->platform,
        ]);

        // Use the role provided by the channel agent in the normalized event sender.
        // Channel-specific role resolution (e.g. Telegram getChatMember) is done by the agent.
        $role = $event->sender->role;

        $commandDef = self::PLATFORM_COMMANDS[$commandName] ?? null;

        if (null !== $commandDef) {
            if (!$this->hasMinRole($role, $commandDef['min_role'])) {
                $this->sendReply($event, 'У вас немає дозволу на використання цієї команди.');

                return;
            }

            $this->handlePlatformCommand($commandDef['handler'], $commandName, $commandArgs, $event, $role);

            return;
        }

        if ('/start' === $commandName && null !== $commandArgs && '' !== $commandArgs) {
            return;
        }

        if ($this->routeAgentCommand($commandName, $commandArgs, $event)) {
            return;
        }

        $this->sendReply($event, 'Невідома команда. Використовуйте /help для списку доступних команд.');
    }

    private function handlePlatformCommand(string $handler, string $commandName, ?string $args, NormalizedEvent $event, string $role): void
    {
        $payload = match ($handler) {
            'help' => (new HelpHandler($this->agentRegistry))->handle($event),
            'agents' => (new AgentsListHandler($this->agentRegistry))->handle($event),
            'agent' => $this->handleAgentSubcommand($args, $event, $role),
            default => null,
        };

        if (null !== $payload) {
            $this->channelManager->send(
                $event->platform,
                DeliveryTarget::fromAddress($payload->target->address),
                $payload,
            );
        }
    }

    private function handleAgentSubcommand(?string $args, NormalizedEvent $event, string $role): ?DeliveryPayload
    {
        if (null === $args || '' === $args) {
            $this->sendReply($event, 'Використання: /agent enable <name> або /agent disable <name>');

            return null;
        }

        $parts = explode(' ', $args, 2);
        $action = $parts[0];
        $agentName = trim($parts[1] ?? '');

        if ('' === $agentName) {
            $this->sendReply($event, 'Вкажіть назву агента. Використовуйте /agents для списку.');

            return null;
        }

        return match ($action) {
            'enable' => (new AgentEnableHandler($this->agentRegistry))->handle($event, $agentName, $role),
            'disable' => (new AgentDisableHandler($this->agentRegistry))->handle($event, $agentName),
            default => $this->buildReply($event, 'Невідома дія. Використовуйте: /agent enable <name> або /agent disable <name>'),
        };
    }

    private function routeAgentCommand(string $commandName, ?string $args, NormalizedEvent $event): bool
    {
        foreach ($this->agentRegistry->findEnabled() as $agent) {
            /** @var array<string, mixed> $manifest */
            $manifest = is_string($agent['manifest'])
                ? json_decode($agent['manifest'], true, 512, JSON_THROW_ON_ERROR)
                : $agent['manifest'];

            $commands = (array) ($manifest['commands'] ?? []);

            if (in_array($commandName, $commands, true)) {
                $this->logger->info('Routing command to agent', [
                    'command' => $commandName,
                    'agent' => $agent['name'],
                ]);

                return true;
            }
        }

        return false;
    }

    private function hasMinRole(string $userRole, string $minRole): bool
    {
        $userLevel = self::ROLE_HIERARCHY[$userRole] ?? 0;
        $minLevel = self::ROLE_HIERARCHY[$minRole] ?? 0;

        return $userLevel >= $minLevel;
    }

    private function sendReply(NormalizedEvent $event, string $text): void
    {
        $payload = $this->buildReply($event, $text);
        $this->channelManager->send(
            $event->platform,
            DeliveryTarget::fromAddress($payload->target->address),
            $payload,
        );
    }

    private function buildReply(NormalizedEvent $event, string $text): DeliveryPayload
    {
        $address = null !== $event->chat->threadId
            ? $event->chat->id.':'.$event->chat->threadId
            : $event->chat->id;

        return new DeliveryPayload(
            botId: $event->botId,
            target: DeliveryTarget::fromAddress($address),
            text: $text,
        );
    }
}
