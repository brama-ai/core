<?php

declare(strict_types=1);

namespace App\AgentDiscovery;

use Psr\Log\LoggerInterface;

final class AgentDiscoveryService
{
    private const TRAEFIK_API_URL = 'http://traefik:8080/api/http/services';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Query Traefik API and return list of agent service descriptors.
     *
     * @return list<array{hostname: string, port: int}>
     */
    public function discoverAgents(): array
    {
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 5, 'ignore_errors' => true],
        ]);

        set_error_handler(static fn (): bool => true);
        try {
            $raw = file_get_contents(self::TRAEFIK_API_URL, false, $context);
        } finally {
            restore_error_handler();
        }

        if (false === $raw) {
            $this->logger->warning('AgentDiscoveryService: could not reach Traefik API at '.self::TRAEFIK_API_URL);

            return [];
        }

        try {
            /** @var list<array<string, mixed>> $services */
            $services = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning('AgentDiscoveryService: Traefik API returned invalid JSON', ['error' => $e->getMessage()]);

            return [];
        }

        return $this->filterAgentServices($services);
    }

    /**
     * @param list<array<string, mixed>> $services
     *
     * @return list<array{hostname: string, port: int}>
     */
    private function filterAgentServices(array $services): array
    {
        $result = [];

        foreach ($services as $service) {
            $name = isset($service['name']) ? (string) $service['name'] : '';

            // Only Docker-provider services ending with -agent
            if (!str_ends_with($name, '-agent@docker')) {
                continue;
            }

            $hostname = str_replace('@docker', '', $name);
            $port = $this->extractPort($service);

            $result[] = ['hostname' => $hostname, 'port' => $port];
            $this->logger->debug('AgentDiscoveryService: found agent service', ['hostname' => $hostname, 'port' => $port]);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $service
     */
    private function extractPort(array $service): int
    {
        // Traefik v3: loadBalancer.servers[].url
        $servers = is_array($service['loadBalancer']['servers'] ?? null)
            ? $service['loadBalancer']['servers']
            : [];

        foreach ($servers as $server) {
            if (is_array($server) && isset($server['url'])) {
                $parsed = parse_url((string) $server['url']);
                if (isset($parsed['port'])) {
                    return (int) $parsed['port'];
                }
            }
        }

        // Fallback: Traefik v2 serverStatus keys
        /** @var array<string, string> $serverStatus */
        $serverStatus = is_array($service['serverStatus'] ?? null) ? $service['serverStatus'] : [];

        foreach (array_keys($serverStatus) as $url) {
            $parsed = parse_url((string) $url);
            if (isset($parsed['port'])) {
                return (int) $parsed['port'];
            }
        }

        return 80;
    }
}
