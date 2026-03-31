<?php

declare(strict_types=1);

namespace App\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\Command\Handler\AgentDisableHandler as ChannelAgentDisableHandler;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\Service\TelegramSenderInterface;

/**
 * @deprecated Use \App\Channel\Command\Handler\AgentDisableHandler instead. Will be removed in Phase 5.
 */
final class AgentDisableHandler
{
    public function __construct(
        private readonly AgentRegistryInterface $agentRegistry,
    ) {
    }

    public function handle(NormalizedEvent $event, TelegramSenderInterface $sender, string $agentName): void
    {
        $payload = (new ChannelAgentDisableHandler($this->agentRegistry))->handle($event, $agentName);
        $options = [];
        if (null !== $event->chat->threadId) {
            $options['thread_id'] = $event->chat->threadId;
        }

        $sender->send($event->botId, $event->chat->id, $payload->text, $options);
    }
}
