<?php

declare(strict_types=1);

namespace App\Telegram\Command\Handler;

use App\AgentRegistry\AgentRegistryInterface;
use App\Channel\Command\Handler\AgentsListHandler as ChannelAgentsListHandler;
use App\Telegram\DTO\NormalizedEvent;
use App\Telegram\Service\TelegramSenderInterface;

/**
 * @deprecated Use \App\Channel\Command\Handler\AgentsListHandler instead. Will be removed in Phase 5.
 */
final class AgentsListHandler
{
    public function __construct(
        private readonly AgentRegistryInterface $agentRegistry,
    ) {
    }

    public function handle(NormalizedEvent $event, TelegramSenderInterface $sender): void
    {
        $payload = (new ChannelAgentsListHandler($this->agentRegistry))->handle($event);
        $options = [];
        if (null !== $event->chat->threadId) {
            $options['thread_id'] = $event->chat->threadId;
        }

        $sender->send($event->botId, $event->chat->id, $payload->text, $options);
    }
}
