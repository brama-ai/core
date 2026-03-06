<?php

declare(strict_types=1);

namespace App\AgentInstaller;

final class AgentMigrationTrigger
{
    public function __construct(
        private readonly string $internalToken,
    ) {
    }

    /**
     * Trigger agent migrations via its internal API endpoint.
     *
     * @param array<string, mixed> $manifest the agent manifest (used to derive base URL from health_url)
     *
     * @throws AgentInstallException if migration fails or agent is unreachable
     */
    public function triggerMigrations(array $manifest): void
    {
        $baseUrl = $this->resolveBaseUrl($manifest);

        if (null === $baseUrl) {
            return;
        }

        $url = rtrim($baseUrl, '/').'/api/v1/internal/migrate';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => sprintf("X-Platform-Internal-Token: %s\r\nContent-Type: application/json", $this->internalToken),
                'content' => '{}',
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (false === $response) {
            throw new AgentInstallException(sprintf('Migration trigger failed: could not reach %s', $url));
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response, true) ?? [];

        $status = $decoded['status'] ?? null;

        if ('ok' !== $status) {
            $error = $decoded['error'] ?? $response;
            throw new AgentInstallException(sprintf('Migration failed for agent: %s', (string) $error));
        }
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function resolveBaseUrl(array $manifest): ?string
    {
        $healthUrl = $manifest['health_url'] ?? null;

        if (is_string($healthUrl) && '' !== $healthUrl) {
            $parsed = parse_url($healthUrl);

            if (false !== $parsed && isset($parsed['scheme'], $parsed['host'])) {
                $port = isset($parsed['port']) ? sprintf(':%d', $parsed['port']) : '';

                return sprintf('%s://%s%s', $parsed['scheme'], $parsed['host'], $port);
            }
        }

        $url = $manifest['url'] ?? $manifest['a2a_endpoint'] ?? null;

        if (is_string($url) && '' !== $url) {
            $parsed = parse_url($url);

            if (false !== $parsed && isset($parsed['scheme'], $parsed['host'])) {
                $port = isset($parsed['port']) ? sprintf(':%d', $parsed['port']) : '';

                return sprintf('%s://%s%s', $parsed['scheme'], $parsed['host'], $port);
            }
        }

        return null;
    }
}
