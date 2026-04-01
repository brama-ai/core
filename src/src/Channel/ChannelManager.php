<?php

declare(strict_types=1);

namespace App\Channel;

use App\A2AGateway\A2AClientInterface;
use App\Channel\DTO\DeliveryPayload;
use App\Channel\DTO\DeliveryResult;
use App\Channel\DTO\DeliveryTarget;
use Psr\Log\LoggerInterface;

/**
 * Outbound routing: resolves the channel agent via ChannelRegistry,
 * retrieves the credential via ChannelCredentialVault, and calls
 * channel.sendOutbound via A2AClientInterface.
 */
final class ChannelManager implements ChannelManagerInterface
{
    public function __construct(
        private readonly ChannelRegistry $registry,
        private readonly ChannelCredentialVault $vault,
        private readonly A2AClientInterface $a2a,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Send a message through the appropriate channel agent.
     *
     * @throws \RuntimeException when no agent is registered for the channel type
     */
    public function send(string $channelType, DeliveryTarget $target, DeliveryPayload $payload): DeliveryResult
    {
        $agentSkill = $this->registry->resolveAgent($channelType);

        $credentialRef = $this->vault->getCredentialRef($payload->botId);

        $traceId = bin2hex(random_bytes(16));
        $requestId = bin2hex(random_bytes(8));

        $this->logger->info('ChannelManager sending outbound message', [
            'channel_type' => $channelType,
            'agent_skill' => $agentSkill,
            'bot_id' => $payload->botId,
            'target' => $target->address,
            'trace_id' => $traceId,
        ]);

        $result = $this->a2a->invoke(
            'channel.sendOutbound',
            [
                'target' => [
                    'address' => $target->address,
                    'chatId' => $target->chatId,
                    'threadId' => $target->threadId,
                ],
                'payload' => [
                    'botId' => $payload->botId,
                    'text' => $payload->text,
                    'contentType' => $payload->contentType,
                ],
                'credentialRef' => $credentialRef,
            ],
            $traceId,
            $requestId,
        );

        return $this->buildDeliveryResult($result);
    }

    /**
     * Invoke a channel-specific admin action via the channel agent.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException when no agent is registered for the channel type
     */
    public function adminAction(string $channelType, string $channelInstanceId, string $action, array $params = []): array
    {
        $this->registry->resolveAgent($channelType);

        $traceId = bin2hex(random_bytes(16));
        $requestId = bin2hex(random_bytes(8));

        $this->logger->info('ChannelManager invoking admin action', [
            'channel_type' => $channelType,
            'channel_instance_id' => $channelInstanceId,
            'action' => $action,
            'trace_id' => $traceId,
        ]);

        return $this->a2a->invoke(
            'channel.adminAction',
            [
                'action' => $action,
                'channelInstanceId' => $channelInstanceId,
                'params' => $params,
            ],
            $traceId,
            $requestId,
        );
    }

    /**
     * @param array<string, mixed> $result
     */
    private function buildDeliveryResult(array $result): DeliveryResult
    {
        if ('failed' === ($result['status'] ?? null)) {
            return DeliveryResult::failure((string) ($result['reason'] ?? 'channel_send_failed'));
        }

        $externalMessageId = isset($result['message_id'])
            ? (string) $result['message_id']
            : null;

        return new DeliveryResult(
            success: true,
            externalMessageId: $externalMessageId,
        );
    }
}
