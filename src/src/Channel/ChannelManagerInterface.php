<?php

declare(strict_types=1);

namespace App\Channel;

use App\Channel\DTO\DeliveryPayload;
use App\Channel\DTO\DeliveryResult;
use App\Channel\DTO\DeliveryTarget;

interface ChannelManagerInterface
{
    /**
     * Send a message through the appropriate channel agent.
     *
     * @throws \RuntimeException when no agent is registered for the channel type
     */
    public function send(string $channelType, DeliveryTarget $target, DeliveryPayload $payload): DeliveryResult;

    /**
     * Invoke a channel-specific admin action via the channel agent.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when no agent is registered for the channel type
     */
    public function adminAction(string $channelType, string $channelInstanceId, string $action, array $params = []): array;
}
