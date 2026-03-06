<?php

declare(strict_types=1);

namespace App\AgentDiscovery;

use Psr\Log\LoggerInterface;

final class AgentManifestFetcher
{
    private const MANIFEST_PATH = '/api/v1/manifest';
    private const TIMEOUT = 5;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Fetch manifest from agent. Returns parsed JSON array or null on failure.
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

        set_error_handler(static fn (): bool => true);
        try {
            $raw = file_get_contents($url, false, $context);
            $headers = $http_response_header;
        } finally {
            restore_error_handler();
        }

        if (false === $raw) {
            $this->logger->info('AgentManifestFetcher: could not reach {url}', ['url' => $url]);

            return null;
        }

        $httpCode = $this->extractHttpCode($headers);
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logger->info('AgentManifestFetcher: {url} returned HTTP {code}', ['url' => $url, 'code' => $httpCode]);

            return null;
        }

        try {
            /** @var array<string, mixed> $manifest */
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return $manifest;
        } catch (\JsonException $e) {
            $this->logger->info('AgentManifestFetcher: {url} returned invalid JSON', ['url' => $url, 'error' => $e->getMessage()]);

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
