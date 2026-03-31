<?php

declare(strict_types=1);

namespace App\Controller\Api\Webhook;

use App\A2AGateway\A2AClientInterface;
use App\Channel\ChannelRegistry;
use App\Channel\Command\PlatformCommandRouter;
use App\Channel\ConversationTracker;
use App\Channel\DTO\NormalizedChat;
use App\Channel\DTO\NormalizedEvent;
use App\Channel\DTO\NormalizedMessage;
use App\Channel\DTO\NormalizedSender;
use App\Channel\EventBus\ChannelEventPublisher;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Generic channel webhook controller.
 *
 * Route: /api/v1/webhook/{channelType}/{channelId}
 *
 * Flow:
 *   1. Resolve channel agent via ChannelRegistry
 *   2. Validate webhook via channel.validateWebhook A2A skill
 *   3. Normalize inbound payload via channel.normalizeInbound A2A skill
 *   4. Track conversation via ConversationTracker
 *   5. Route platform commands via PlatformCommandRouter
 *   6. Publish remaining events via ChannelEventPublisher
 *
 * Legacy alias: /api/v1/webhook/telegram/{botId} → channelType=telegram
 */
final class ChannelWebhookController extends AbstractController
{
    public function __construct(
        private readonly ChannelRegistry $channelRegistry,
        private readonly A2AClientInterface $a2a,
        private readonly ConversationTracker $conversationTracker,
        private readonly PlatformCommandRouter $commandRouter,
        private readonly ChannelEventPublisher $eventPublisher,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/v1/webhook/{channelType}/{channelId}', name: 'api_webhook_channel', methods: ['POST'])]
    #[Route('/api/v1/webhook/telegram/{channelId}', name: 'api_webhook_channel_telegram_legacy', defaults: ['channelType' => 'telegram'], methods: ['POST'])]
    public function __invoke(Request $request, string $channelType, string $channelId): Response
    {
        // Resolve agent for this channel type
        try {
            $this->channelRegistry->resolveAgent($channelType);
        } catch (\RuntimeException) {
            $this->logger->warning('No agent registered for channel type', [
                'channel_type' => $channelType,
                'channel_id' => $channelId,
            ]);

            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $traceId = bin2hex(random_bytes(16));
        $requestId = bin2hex(random_bytes(8));
        $rawBody = $request->getContent();

        // 1. Validate webhook via agent
        $validationResult = $this->a2a->invoke(
            'channel.validateWebhook',
            [
                'channelId' => $channelId,
                'headers' => $request->headers->all(),
                'body' => $rawBody,
            ],
            $traceId,
            $requestId,
        );

        if (!($validationResult['valid'] ?? false)) {
            $this->logger->warning('Channel webhook validation failed', [
                'channel_type' => $channelType,
                'channel_id' => $channelId,
                'ip' => $request->getClientIp(),
                'reason' => $validationResult['reason'] ?? 'unknown',
            ]);

            return new Response('', Response::HTTP_FORBIDDEN);
        }

        // Parse raw payload
        try {
            /** @var array<string, mixed> $rawPayload */
            $rawPayload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning('Invalid JSON in channel webhook', [
                'channel_type' => $channelType,
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);

            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        // 2. Normalize inbound payload via agent
        $normalizedData = $this->a2a->invoke(
            'channel.normalizeInbound',
            [
                'rawPayload' => $rawPayload,
                'channelId' => $channelId,
                'headers' => $request->headers->all(),
            ],
            $traceId,
            $requestId,
        );

        if ('failed' === ($normalizedData['status'] ?? null)) {
            $this->logger->warning('Channel normalization failed', [
                'channel_type' => $channelType,
                'channel_id' => $channelId,
                'reason' => $normalizedData['reason'] ?? 'unknown',
            ]);

            // Return 200 to prevent retries from the channel provider
            return new JsonResponse(null, Response::HTTP_OK);
        }

        // Build NormalizedEvent from agent response
        $events = $this->buildNormalizedEvents($normalizedData, $channelType, $channelId, $traceId, $requestId);

        foreach ($events as $event) {
            try {
                // 3. Track conversation
                $this->conversationTracker->track($channelType, $event);

                // 4. Route platform commands
                if ('command_received' === $event->eventType) {
                    $this->commandRouter->route($event);
                }

                // 5. Publish to EventBus
                $this->eventPublisher->publish($event);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to process channel event', [
                    'channel_type' => $channelType,
                    'channel_id' => $channelId,
                    'event_type' => $event->eventType,
                    'error' => $e->getMessage(),
                    'trace_id' => $traceId,
                ]);
            }
        }

        // Always return 200 to prevent retries
        return new JsonResponse(null, Response::HTTP_OK);
    }

    /**
     * Build NormalizedEvent objects from the agent normalization response.
     *
     * The agent may return a single event or a list of events.
     *
     * @param array<string, mixed> $normalizedData
     *
     * @return list<NormalizedEvent>
     */
    private function buildNormalizedEvents(
        array $normalizedData,
        string $channelType,
        string $channelId,
        string $traceId,
        string $requestId,
    ): array {
        // Agent may return a single event or a list under 'events' key
        $eventDataList = isset($normalizedData['events']) && is_array($normalizedData['events'])
            ? $normalizedData['events']
            : [$normalizedData];

        $events = [];
        foreach ($eventDataList as $eventData) {
            if (!is_array($eventData) || !isset($eventData['event_type'])) {
                continue;
            }

            $events[] = $this->buildNormalizedEvent($eventData, $channelType, $channelId, $traceId, $requestId);
        }

        return $events;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildNormalizedEvent(
        array $data,
        string $channelType,
        string $channelId,
        string $traceId,
        string $requestId,
    ): NormalizedEvent {
        /** @var array<string, mixed> $chatData */
        $chatData = is_array($data['chat'] ?? null) ? $data['chat'] : [];
        /** @var array<string, mixed> $senderData */
        $senderData = is_array($data['sender'] ?? null) ? $data['sender'] : [];
        /** @var array<string, mixed> $messageData */
        $messageData = is_array($data['message'] ?? null) ? $data['message'] : [];

        $chat = new NormalizedChat(
            id: (string) ($chatData['id'] ?? ''),
            type: (string) ($chatData['type'] ?? 'unknown'),
            title: isset($chatData['title']) ? (string) $chatData['title'] : null,
            threadId: isset($chatData['thread_id']) ? (string) $chatData['thread_id'] : null,
        );

        $sender = new NormalizedSender(
            id: (string) ($senderData['id'] ?? ''),
            username: isset($senderData['username']) ? (string) $senderData['username'] : null,
            firstName: isset($senderData['first_name']) ? (string) $senderData['first_name'] : null,
            isBot: (bool) ($senderData['is_bot'] ?? false),
        );

        $message = new NormalizedMessage(
            id: (string) ($messageData['id'] ?? $messageData['message_id'] ?? ''),
            text: isset($messageData['text']) ? (string) $messageData['text'] : null,
            commandName: isset($messageData['command_name']) ? (string) $messageData['command_name'] : null,
            commandArgs: isset($messageData['command_args']) ? (string) $messageData['command_args'] : null,
        );

        return new NormalizedEvent(
            eventType: (string) ($data['event_type'] ?? 'message_received'),
            platform: $channelType,
            botId: $channelId,
            chat: $chat,
            sender: $sender,
            message: $message,
            traceId: (string) ($data['trace_id'] ?? $traceId),
            requestId: (string) ($data['request_id'] ?? $requestId),
            rawUpdateId: (int) ($data['raw_update_id'] ?? 0),
        );
    }
}
