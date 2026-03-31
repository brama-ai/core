<?php

declare(strict_types=1);

namespace App\Telegram\Command;

use App\AgentRegistry\AgentRegistryInterface;
use App\Telegram\Command\Handler\AgentDisableHandler;
use App\Telegram\Command\Handler\AgentEnableHandler;
use App\Telegram\Command\Handler\AgentsListHandler;
use App\Telegram\Command\Handler\HelpHandler;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\Service\TelegramRoleResolverInterface;
use App\Telegram\Service\TelegramSenderInterface;
use Psr\Log\LoggerInterface;

/**
 * @deprecated Use \App\Channel\Command\PlatformCommandRouter instead. Will be removed in Phase 5.
 */
final class TelegramCommandRouter
{
    /** @var array<string, array{handler: string, min_role: string}> */
    private const PLATFORM_COMMANDS = [
        '/help' => ['handler' => 'help', 'min_role' => 'user'],
        '/agents' => ['handler' => 'agents', 'min_role' => 'user'],
        '/agent' => ['handler' => 'agent', 'min_role' => 'moderator'],
    ];

    private const ROLE_HIERARCHY = ['admin' => 3, 'moderator' => 2, 'user' => 1];

    public function __construct(
        private readonly TelegramRoleResolverInterface $roleResolver,
        private readonly TelegramSenderInterface $sender,
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

        $this->logger->info('Routing Telegram command', [
            'command' => $commandName,
            'args' => $commandArgs,
            'sender' => $event->sender->id,
            'chat_id' => $event->chat->id,
        ]);

        // Resolve sender role
        $role = $this->roleResolver->resolve($event->botId, $event->chat->id, $event->sender->id);

        // Check if it's a platform command
        $commandDef = self::PLATFORM_COMMANDS[$commandName] ?? null;

        if (null !== $commandDef) {
            if (!$this->hasMinRole($role, $commandDef['min_role'])) {
                $this->sendReply($event, 'У вас немає дозволу на використання цієї команди.');

                return;
            }

            $this->handlePlatformCommand($commandDef['handler'], $commandName, $commandArgs, $event, $role);

            return;
        }

        // Check if it's a /start with deep-link payload
        if ('/start' === $commandName && null !== $commandArgs && '' !== $commandArgs) {
            // Deep-link start parameters are handled by ConversationManager
            return;
        }

        // Check agent-declared commands
        if ($this->routeAgentCommand($commandName, $commandArgs, $event)) {
            return;
        }

        // Unknown command
        $this->sendReply($event, 'Невідома команда. Використовуйте /help для списку доступних команд.');
    }

    private function handlePlatformCommand(string $handler, string $commandName, ?string $args, NormalizedEvent $event, string $role): void
    {
        match ($handler) {
            'help' => (new HelpHandler($this->agentRegistry))->handle($event, $this->sender),
            'agents' => (new AgentsListHandler($this->agentRegistry))->handle($event, $this->sender),
            'agent' => $this->handleAgentSubcommand($args, $event, $role),
            default => null,
        };
    }

    private function handleAgentSubcommand(?string $args, NormalizedEvent $event, string $role): void
    {
        if (null === $args || '' === $args) {
            $this->sendReply($event, 'Використання: /agent enable <name> або /agent disable <name>');

            return;
        }

        $parts = explode(' ', $args, 2);
        $action = $parts[0];
        $agentName = trim($parts[1] ?? '');

        if ('' === $agentName) {
            $this->sendReply($event, 'Вкажіть назву агента. Використовуйте /agents для списку.');

            return;
        }

        match ($action) {
            'enable' => (new AgentEnableHandler($this->agentRegistry))->handle($event, $this->sender, $agentName, $role),
            'disable' => (new AgentDisableHandler($this->agentRegistry))->handle($event, $this->sender, $agentName),
            default => $this->sendReply($event, 'Невідома дія. Використовуйте: /agent enable <name> або /agent disable <name>'),
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

                // Agent commands are dispatched through EventBus as command_received events
                // The webhook controller already published this event
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
        $options = [];
        if (null !== $event->chat->threadId) {
            $options['thread_id'] = $event->chat->threadId;
        }

        $this->sender->send($event->botId, $event->chat->id, $text, $options);
    }
}
