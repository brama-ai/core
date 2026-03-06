<?php

declare(strict_types=1);

namespace App\A2AGateway;

use App\Logging\TraceEvent;
use Psr\Log\LoggerInterface;

final class AgentCardFetcher
{
    private const MANIFEST_PATH = '/api/v1/manifest';
    private const TIMEOUT = 5;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Fetch Agent Card from agent. Returns parsed JSON array or null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(string $hostname, int $port = 80): ?array
    {
        $url = sprintf('http://%s:%d%s', $hostname, $port, self::MANIFEST_PATH);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        $start = microtime(true);

        set_error_handler(static fn (): bool => true);
        try {
            $raw = file_get_contents($url, false, $context);
            /** @var list<string> $headers */
            $headers = http_get_last_response_headers() ?: [];
        } finally {
            restore_error_handler();
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        if (false === $raw) {
            $this->logger->warning(
                'Agent Card fetch failed: could not reach endpoint',
                TraceEvent::build('core.agent_card.fetch_failed', 'agent_card_fetch', 'core', 'failed', [
                    'target_app' => $hostname,
                    'duration_ms' => $durationMs,
                    'error_code' => 'connection_failed',
                ]),
            );

            return null;
        }

        $httpCode = $this->extractHttpCode($headers);
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logger->warning(
                'Agent Card fetch failed: HTTP error',
                TraceEvent::build('core.agent_card.fetch_failed', 'agent_card_fetch', 'core', 'failed', [
                    'target_app' => $hostname,
                    'duration_ms' => $durationMs,
                    'http_status_code' => $httpCode,
                    'error_code' => 'http_error',
                ]),
            );

            return null;
        }

        try {
            /** @var array<string, mixed> $manifest */
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            $this->logger->info(
                'Agent Card fetched successfully',
                TraceEvent::build('core.agent_card.fetch_completed', 'agent_card_fetch', 'core', 'completed', [
                    'target_app' => $hostname,
                    'duration_ms' => $durationMs,
                    'http_status_code' => $httpCode,
                    'agent_name' => (string) ($manifest['name'] ?? ''),
                    'agent_version' => (string) ($manifest['version'] ?? ''),
                ]),
            );

            return $manifest;
        } catch (\JsonException $e) {
            $this->logger->warning(
                'Agent Card fetch failed: invalid JSON',
                TraceEvent::build('core.agent_card.fetch_failed', 'agent_card_fetch', 'core', 'failed', [
                    'target_app' => $hostname,
                    'duration_ms' => $durationMs,
                    'http_status_code' => $httpCode,
                    'error_code' => 'invalid_json',
                    'exception' => $e,
                ]),
            );

            return null;
        }
    }

    /**
     * @param list<string> $headers
     */
    private function extractHttpCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $header, $m)) {
                return (int) $m[1];
            }
        }

        return 200;
    }
}
