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
     * @param array<string, mixed> $input
     * @param array<string, mixed> $result
     */
    public function recordOpenClawInvoke(
        string $traceId,
        string $requestId,
        string $tool,
        array $input,
        array $result,
        int $durationMs,
    ): void {
        $traceId = TraceContext::normalizeTraceId($traceId);
        $endTs = microtime(true);
        $startTs = $endTs - max(0, $durationMs) / 1000;

        $events = [
            $this->traceCreateEvent(
                $traceId,
                'openclaw.invoke',
                $this->iso8601FromMicrotime($startTs),
                [
                    'service' => 'core',
                    'source' => 'openclaw',
                    'request_id' => $requestId,
                    'tool' => $tool,
                    'duration_ms' => $durationMs,
                    'status' => (string) ($result['status'] ?? 'unknown'),
                ],
                $input,
                $result,
                $requestId,
            ),
            $this->spanCreateEvent(
                $traceId,
                'core.openclaw.invoke',
                $this->iso8601FromMicrotime($startTs),
                $this->iso8601FromMicrotime($endTs),
                [
                    'service' => 'core',
                    'request_id' => $requestId,
                    'tool' => $tool,
                    'duration_ms' => $durationMs,
                    'status' => (string) ($result['status'] ?? 'unknown'),
                ],
                $input,
                $result,
            ),
        ];

        $this->ingest($events);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $result
     */
    public function recordA2ACall(
        string $traceId,
        string $requestId,
        string $tool,
        string $agent,
        int $durationMs,
        string $status,
        int $httpStatusCode,
        array $input,
        array $result,
    ): void {
        $traceId = TraceContext::normalizeTraceId($traceId);
        $endTs = microtime(true);
        $startTs = $endTs - max(0, $durationMs) / 1000;

        $events = [
            $this->spanCreateEvent(
                $traceId,
                'core.a2a.call',
                $this->iso8601FromMicrotime($startTs),
                $this->iso8601FromMicrotime($endTs),
                [
                    'service' => 'core',
                    'request_id' => $requestId,
                    'a2a.tool' => $tool,
                    'a2a.target_agent' => $agent,
                    'http.status_code' => $httpStatusCode,
                    'status' => $status,
                    'duration_ms' => $durationMs,
                ],
                $input,
                $result,
            ),
        ];

        $this->ingest($events);
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function ingest(array $events): void
    {
        if (!$this->isConfigured()) {
            return;
        }
        try {
            $payload = json_encode(['batch' => $events], JSON_THROW_ON_ERROR);
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", [
                        'Content-Type: application/json',
                        'Authorization: Basic '.base64_encode($this->publicKey.':'.$this->secretKey),
                        'Content-Length: '.strlen($payload),
                    ])."\r\n",
                    'content' => $payload,
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
                    'service' => 'core',
                    'event' => 'langfuse.ingestion_failed',
                ]);
            }
        } catch (\Throwable $exception) {
            $this->logger->warning('Langfuse ingestion exception', [
                'service' => 'core',
                'event' => 'langfuse.ingestion_exception',
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function isConfigured(): bool
    {
        return $this->enabled && '' !== trim($this->baseUrl) && '' !== trim($this->publicKey) && '' !== trim($this->secretKey);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     *
     * @return array<string, mixed>
     */
    private function traceCreateEvent(
        string $traceId,
        string $name,
        string $timestamp,
        array $metadata,
        array $input,
        array $output,
        string $requestId,
    ): array {
        return [
            'id' => $this->eventId(),
            'timestamp' => $timestamp,
            'type' => 'trace-create',
            'body' => [
                'id' => $traceId,
                'name' => $name,
                'timestamp' => $timestamp,
                'metadata' => $metadata,
                'input' => $input,
                'output' => $output,
                'sessionId' => $requestId,
                'userId' => $requestId,
                'environment' => $this->environment,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     *
     * @return array<string, mixed>
     */
    private function spanCreateEvent(
        string $traceId,
        string $name,
        string $startTime,
        string $endTime,
        array $metadata,
        array $input,
        array $output,
    ): array {
        return [
            'id' => $this->eventId(),
            'timestamp' => $endTime,
            'type' => 'span-create',
            'body' => [
                'id' => TraceContext::generateSpanId(),
                'traceId' => $traceId,
                'name' => $name,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'input' => $input,
                'output' => $output,
                'metadata' => $metadata,
                'level' => 'DEFAULT',
                'environment' => $this->environment,
            ],
        ];
    }

    private function eventId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function iso8601FromMicrotime(float $timestamp): string
    {
        $seconds = (int) floor($timestamp);
        $microseconds = (int) (($timestamp - $seconds) * 1_000_000);
        $date = \DateTimeImmutable::createFromFormat('U u', sprintf('%d %06d', $seconds, $microseconds), new \DateTimeZone('UTC'));

        return false === $date ? gmdate(DATE_ATOM) : $date->format('Y-m-d\TH:i:s.v\Z');
    }
}
