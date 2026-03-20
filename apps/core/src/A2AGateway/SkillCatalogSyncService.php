<?php

declare(strict_types=1);

namespace App\A2AGateway;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class SkillCatalogSyncService
{
    private const SYNC_STATUS_KEY = 'openclaw_sync_status';
    private const SYNC_STATUS_TTL = 3600;

    public function __construct(
        private readonly SkillCatalogBuilderInterface $catalogBuilder,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $pushUrl,
        private readonly string $gatewayToken,
    ) {
    }

    /**
     * Push the current skill catalog to OpenClaw's reload endpoint.
     * Fails gracefully — never throws; stores result in cache.
     */
    public function pushDiscovery(): void
    {
        if ('' === $this->pushUrl) {
            return;
        }

        try {
            $payload = $this->catalogBuilder->build();
            $statusCode = $this->postDiscovery($payload);
            $success = $statusCode >= 200 && $statusCode < 300;

            if ($success) {
                $this->logger->info('Discovery pushed to OpenClaw', [
                    'http_status' => $statusCode,
                ]);
            } else {
                $this->logger->warning('Discovery push to OpenClaw failed', [
                    'http_status' => $statusCode,
                ]);
            }

            $this->saveSyncStatus([
                'status' => $success ? 'ok' : 'failed',
                'timestamp' => time(),
                'error' => $success ? null : "HTTP {$statusCode}",
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Discovery push to OpenClaw exception', [
                'exception' => $e,
            ]);

            $this->saveSyncStatus([
                'status' => 'failed',
                'timestamp' => time(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSyncStatus(): ?array
    {
        $item = $this->cache->getItem(self::SYNC_STATUS_KEY);

        if (!$item->isHit()) {
            return null;
        }

        /** @var array<string, mixed> $status */
        $status = $item->get();

        return $status;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postDiscovery(array $payload): int
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $token = $this->gatewayToken;

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$token}",
                    'Content-Length: '.strlen($body),
                ]),
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static fn (): bool => true);

        try {
            $result = file_get_contents($this->pushUrl, false, $context);
            $headers = $http_response_header;
        } finally {
            restore_error_handler();
        }

        if (false === $result) {
            throw new \RuntimeException("Failed to connect to OpenClaw push URL: {$this->pushUrl}");
        }

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $header, $m)) {
                return (int) $m[1];
            }
        }

        return 200;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function saveSyncStatus(array $status): void
    {
        $item = $this->cache->getItem(self::SYNC_STATUS_KEY);
        $item->set($status);
        $item->expiresAfter(self::SYNC_STATUS_TTL);
        $this->cache->save($item);
    }
}
