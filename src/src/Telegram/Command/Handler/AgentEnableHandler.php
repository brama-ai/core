<?php

declare(strict_types=1);

namespace App\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\Command\Handler\AgentEnableHandler as ChannelAgentEnableHandler;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\Service\TelegramSenderInterface;

/**
 * @deprecated Use \App\Channel\Command\Handler\AgentEnableHandler instead. Will be removed in Phase 5.
 */
final class AgentEnableHandler
{
    public function __construct(
        private readonly AgentRegistryInterface $agentRegistry,
    ) {
    }

    public function handle(NormalizedEvent $event, TelegramSenderInterface $sender, string $agentName, string $role): void
    {
        $payload = (new ChannelAgentEnableHandler($this->agentRegistry))->handle($event, $agentName, $role);
        $options = [];
        if (null !== $event->chat->threadId) {
            $options['thread_id'] = $event->chat->threadId;
        }

        $sender->send($event->botId, $event->chat->id, $payload->text, $options);
    }
}
