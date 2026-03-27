<?php

declare(strict_types=1);

namespace App\A2AGateway\Discovery;

use Psr\Log\LoggerInterface;

final class KubernetesDiscoveryProvider implements AgentDiscoveryProviderInterface
{
    public const SERVICE_ACCOUNT_TOKEN_PATH = '/var/run/secrets/kubernetes.io/serviceaccount/token';

    private const SERVICE_ACCOUNT_NAMESPACE_PATH = '/var/run/secrets/kubernetes.io/serviceaccount/namespace';
    private const SERVICE_ACCOUNT_CA_PATH = '/var/run/secrets/kubernetes.io/serviceaccount/ca.crt';
    private const KUBERNETES_API_BASE_URL = 'https://kubernetes.default.svc';
    private const TIMEOUT_SECONDS = 5;

    /**
     * @var \Closure(string): (string|false)
     */
    private readonly \Closure $readFile;

    /**
     * @var \Closure(string, string): array{status: int, body: string|false}
     */
    private readonly \Closure $request;

    public function __construct(
        private readonly LoggerInterface $logger,
        ?callable $readFile = null,
        ?callable $request = null,
    ) {
        $this->readFile = null !== $readFile
            ? \Closure::fromCallable($readFile)
            : static fn (string $path): string|false => file_get_contents($path);

        $this->request = null !== $request
            ? \Closure::fromCallable($request)
            : \Closure::fromCallable([$this, 'defaultRequest']);
    }

    /**
     * @return list<array{hostname: string, port: int}>
     */
    public function discover(): array
    {
        $this->logger->info('Starting Kubernetes agent discovery', [
            'event_name' => 'core.discovery.kubernetes_started',
        ]);

        $token = $this->readTrimmedFile(self::SERVICE_ACCOUNT_TOKEN_PATH);
        $namespace = $this->readTrimmedFile(self::SERVICE_ACCOUNT_NAMESPACE_PATH);

        if (null === $token || null === $namespace) {
            $this->logger->warning('Kubernetes agent discovery is unavailable: missing service account credentials', [
                'event_name' => 'core.discovery.kubernetes_credentials_missing',
                'token_path' => self::SERVICE_ACCOUNT_TOKEN_PATH,
                'namespace_path' => self::SERVICE_ACCOUNT_NAMESPACE_PATH,
                'token_exists' => null !== $token,
                'namespace_exists' => null !== $namespace,
            ]);

            return [];
        }

        $this->logger->info('Kubernetes credentials loaded', [
            'event_name' => 'core.discovery.kubernetes_credentials_loaded',
            'namespace' => $namespace,
        ]);

        $url = sprintf(
            '%s/api/v1/namespaces/%s/services?%s',
            self::KUBERNETES_API_BASE_URL,
            rawurlencode($namespace),
            http_build_query(['labelSelector' => 'ai.platform.agent=true']),
        );

        $this->logger->info('Querying Kubernetes API for agent services', [
            'event_name' => 'core.discovery.kubernetes_request_started',
            'url' => $url,
            'namespace' => $namespace,
            'label_selector' => 'ai.platform.agent=true',
        ]);

        $response = ($this->request)($url, $token);
        if (false === $response['body']) {
            $this->logger->warning('Kubernetes agent discovery failed: API request error', [
                'url' => $url,
                'event_name' => 'core.discovery.kubernetes_unreachable',
            ]);

            return [];
        }

        $this->logger->info('Kubernetes API responded', [
            'event_name' => 'core.discovery.kubernetes_response_received',
            'status_code' => $response['status'],
            'response_length' => strlen($response['body']),
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $this->logger->warning('Kubernetes agent discovery failed: non-success status code', [
                'url' => $url,
                'status_code' => $response['status'],
                'response_body' => substr($response['body'], 0, 500),
                'event_name' => 'core.discovery.kubernetes_http_error',
            ]);

            return [];
        }

        try {
            /** @var array{items?: list<array<string, mixed>>} $payload */
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning('Kubernetes agent discovery failed: invalid JSON response', [
                'url' => $url,
                'event_name' => 'core.discovery.kubernetes_invalid_json',
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        /** @var list<array<string, mixed>> $items */
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $agents = [];

        foreach ($items as $service) {
            $serviceName = isset($service['metadata']['name']) ? (string) $service['metadata']['name'] : '';
            if ('' === $serviceName) {
                continue;
            }

            $port = $this->extractPort($service);
            if (null === $port) {
                continue;
            }

            $hostname = sprintf('%s.%s.svc.cluster.local', $serviceName, $namespace);
            $agents[] = ['hostname' => $hostname, 'port' => $port];

            $this->logger->debug('Kubernetes agent discovery: found service', [
                'hostname' => $hostname,
                'port' => $port,
            ]);
        }

        return $agents;
    }

    /**
     * @return array{status: int, body: string|false}
     */
    private function defaultRequest(string $url, string $token): array
    {
        $headers = [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
        ];

        $contextOptions = [
            'http' => [
                'method' => 'GET',
                'timeout' => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];

        if (is_file(self::SERVICE_ACCOUNT_CA_PATH)) {
            $contextOptions['ssl']['cafile'] = self::SERVICE_ACCOUNT_CA_PATH;
        }

        $context = stream_context_create($contextOptions);

        /** @var list<string> $http_response_header */
        $http_response_header = [];
        set_error_handler(static fn (): bool => true);
        try {
            $body = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }

        /** @var list<string> $responseHeaders */
        $responseHeaders = $http_response_header ?: [];

        return [
            'status' => $this->extractHttpCode($responseHeaders),
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $service
     */
    private function extractPort(array $service): ?int
    {
        $ports = is_array($service['spec']['ports'] ?? null)
            ? $service['spec']['ports']
            : [];

        foreach ($ports as $portConfig) {
            if (!is_array($portConfig)) {
                continue;
            }

            if ('http' === (string) ($portConfig['name'] ?? '') && isset($portConfig['port'])) {
                return (int) $portConfig['port'];
            }
        }

        foreach ($ports as $portConfig) {
            if (is_array($portConfig) && isset($portConfig['port'])) {
                return (int) $portConfig['port'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $headers
     */
    private function extractHttpCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function readTrimmedFile(string $path): ?string
    {
        $content = ($this->readFile)($path);
        if (false === $content) {
            return null;
        }

        $trimmed = trim($content);

        return '' === $trimmed ? null : $trimmed;
    }
}
