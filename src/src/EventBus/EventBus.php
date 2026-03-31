<?php

declare(strict_types=1);

namespace App\EventBus;

use App\AgentRegistry\AgentRegistryInterface;
use App\AgentRegistry\ManifestValidator;
use Psr\Log\LoggerInterface;

final class EventBus implements EventBusInterface
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Dispatch a platform event to all enabled agents subscribed to it.
     *
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $eventType, array $payload): void
    {
        $enabledAgents = $this->registry->findEnabled();
        $traceId = (string) ($payload['trace_id'] ?? bin2hex(random_bytes(8)));
        $requestId = (string) ($payload['request_id'] ?? bin2hex(random_bytes(8)));
        $dispatched = 0;
        $start = microtime(true);

        foreach ($enabledAgents as $agent) {
            /** @var array<string, mixed> $manifest */
            $manifest = is_string($agent['manifest'])
                ? json_decode($agent['manifest'], true, 512, JSON_THROW_ON_ERROR)
                : $agent['manifest'];

            $subscribedEvents = (array) ($manifest['events'] ?? []);

            if (!in_array($eventType, $subscribedEvents, true)) {
                continue;
            }

            $a2aEndpoint = ManifestValidator::resolveUrl($manifest);
            if ('' === $a2aEndpoint) {
                $this->logger->warning('Agent has no A2A endpoint, skipping event dispatch', [
                    'agent' => $agent['name'],
                    'event_type' => $eventType,
                ]);
                continue;
            }

            try {
                $this->postEvent($a2aEndpoint, $eventType, $payload, $traceId, $requestId);
                ++$dispatched;
                $this->logger->info('Event dispatched to agent', [
                    'agent' => $agent['name'],
                    'event_type' => $eventType,
                    'trace_id' => $traceId,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Event dispatch failed for agent', [
                    'agent' => $agent['name'],
                    'event_type' => $eventType,
                    'trace_id' => $traceId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);
        $this->logger->info('Event dispatch completed', [
            'event_type' => $eventType,
            'agents_notified' => $dispatched,
            'duration_ms' => $durationMs,
            'trace_id' => $traceId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postEvent(string $url, string $eventType, array $payload, string $traceId, string $requestId): void
    {
        $body = json_encode([
            'event_type' => $eventType,
            'payload' => $payload,
            'trace_id' => $traceId,
            'request_id' => $requestId,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Content-Length: '.strlen($body),
                    'X-Event-Type: '.$eventType,
                    'X-Trace-Id: '.$traceId,
                    'X-Request-Id: '.$requestId,
                ])."\r\n",
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static fn (): bool => true);

        try {
            $result = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        if (false === $result) {
            throw new \RuntimeException("Failed to connect to agent A2A endpoint: {$url}");
        }
    }
}
