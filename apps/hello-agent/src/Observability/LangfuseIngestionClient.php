<?php

declare(strict_types=1);

namespace App\Observability;

use Psr\Log\LoggerInterface;

final class LangfuseIngestionClient
{
    public function __construct(
        private readonly bool $enabled,
        private readonly string $baseUrl,
        private readonly string $publicKey,
        private readonly string $secretKey,
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $result
     */
    public function recordA2ARequest(
        string $traceId,
        string $requestId,
        string $intent,
        array $payload,
        array $result,
        int $durationMs,
    ): void {
        if (!$this->isConfigured()) {
            return;
        }

        $traceId = TraceContext::normalizeTraceId($traceId);
        $endTs = microtime(true);
        $startTs = $endTs - max(0, $durationMs) / 1000;

        $events = [
            [
                'id' => $this->eventId(),
                'timestamp' => $this->iso8601FromMicrotime($startTs),
                'type' => 'trace-create',
                'body' => [
                    'id' => $traceId,
                    'name' => 'hello-agent.a2a',
                    'timestamp' => $this->iso8601FromMicrotime($startTs),
                    'sessionId' => $requestId,
                    'userId' => $requestId,
                    'environment' => $this->environment,
                    'metadata' => [
                        'service' => 'hello-agent',
                        'request_id' => $requestId,
                        'intent' => $intent,
                        'status' => (string) ($result['status'] ?? 'unknown'),
                        'duration_ms' => $durationMs,
                    ],
                    'input' => $payload,
                    'output' => $result,
                ],
            ],
            [
                'id' => $this->eventId(),
                'timestamp' => $this->iso8601FromMicrotime($endTs),
                'type' => 'span-create',
                'body' => [
                    'id' => TraceContext::generateSpanId(),
                    'traceId' => $traceId,
                    'name' => 'hello-agent.a2a.handle',
                    'startTime' => $this->iso8601FromMicrotime($startTs),
                    'endTime' => $this->iso8601FromMicrotime($endTs),
                    'input' => $payload,
                    'output' => $result,
                    'metadata' => [
                        'service' => 'hello-agent',
                        'request_id' => $requestId,
                        'intent' => $intent,
                        'status' => (string) ($result['status'] ?? 'unknown'),
                        'duration_ms' => $durationMs,
                    ],
                    'level' => 'DEFAULT',
                    'environment' => $this->environment,
                ],
            ],
        ];
        try {
            $body = json_encode(['batch' => $events], \JSON_THROW_ON_ERROR);
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", [
                        'Content-Type: application/json',
                        'Authorization: Basic '.base64_encode($this->publicKey.':'.$this->secretKey),
                        'Content-Length: '.\strlen($body),
                    ])."\r\n",
                    'content' => $body,
                    'timeout' => 2,
                    'ignore_errors' => true,
                ],
            ]);

            set_error_handler(static fn (): bool => true);

            try {
                $response = file_get_contents(rtrim($this->baseUrl, '/').'/api/public/ingestion', false, $context);
            } finally {
                restore_error_handler();
            }

            if (false === $response) {
                $this->logger->warning('Langfuse ingestion failed', [
                    'service' => 'hello-agent',
                    'event_name' => 'hello.langfuse.ingestion_failed',
                    'trace_id' => $events[0]['body']['id'],
                ]);
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Langfuse ingestion exception', [
                'service' => 'hello-agent',
                'event_name' => 'hello.langfuse.ingestion_exception',
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function isConfigured(): bool
    {
        return $this->enabled && '' !== trim($this->baseUrl) && '' !== trim($this->publicKey) && '' !== trim($this->secretKey);
    }

    private function eventId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function iso8601FromMicrotime(float $timestamp): string
    {
        $seconds = (int) floor($timestamp);
        $microseconds = (int) (($timestamp - $seconds) * 1_000_000);
        $date = \DateTimeImmutable::createFromFormat('U u', \sprintf('%d %06d', $seconds, $microseconds), new \DateTimeZone('UTC'));

        return false === $date ? gmdate(\DATE_ATOM) : $date->format('Y-m-d\TH:i:s.v\Z');
    }
}
