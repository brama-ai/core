<?php

declare(strict_types=1);

namespace App\AgentInstaller\Strategy;

use App\AgentInstaller\AgentInstallException;

final class OpenSearchInstallStrategy implements InstallStrategyInterface
{
    public function __construct(
        private readonly string $openSearchUrl,
    ) {
    }

    public function provision(array $storageConfig, string $agentName): array
    {
        $collections = $storageConfig['collections'] ?? [];

        if (!is_array($collections) || [] === $collections) {
            throw new AgentInstallException('OpenSearch collections must be a non-empty array');
        }

        $actions = [];

        foreach ($collections as $collection) {
            $indexName = sprintf('%s_%s', str_replace('-', '_', $agentName), $collection);

            if (!$this->indexExists($indexName)) {
                $this->createIndex($indexName);
                $actions[] = sprintf('created_index:%s', $indexName);
            }
        }

        return $actions;
    }

    public function deprovision(array $storageConfig, string $agentName): array
    {
        $collections = $storageConfig['collections'] ?? [];

        if (!is_array($collections) || [] === $collections) {
            return [];
        }

        $actions = [];

        foreach ($collections as $collection) {
            if (!is_string($collection) || '' === $collection) {
                throw new AgentInstallException('OpenSearch collections must contain non-empty string values');
            }

            $indexName = sprintf('%s_%s', str_replace('-', '_', $agentName), $collection);

            if (!$this->indexExists($indexName)) {
                continue;
            }

            $this->deleteIndex($indexName);
            $actions[] = sprintf('deleted_index:%s', $indexName);
        }

        return $actions;
    }

    public function isProvisioned(array $storageConfig): bool
    {
        $collections = $storageConfig['collections'] ?? [];
        $agentName = '';

        foreach ($collections as $collection) {
            $indexName = sprintf('%s_%s', $agentName, $collection);

            if (!$this->indexExists($indexName)) {
                return false;
            }
        }

        return true;
    }

    private function indexExists(string $indexName): bool
    {
        $url = sprintf('%s/%s', rtrim($this->openSearchUrl, '/'), urlencode($indexName));

        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $headers = @get_headers($url, context: $context);

        if (false === $headers || [] === $headers) {
            return false;
        }

        return str_contains($headers[0], '200');
    }

    private function createIndex(string $indexName): void
    {
        $url = sprintf('%s/%s', rtrim($this->openSearchUrl, '/'), urlencode($indexName));
        $body = json_encode([
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => 'Content-Type: application/json',
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (false === $response) {
            throw new AgentInstallException(sprintf('Failed to create OpenSearch index: %s', $indexName));
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response, true);

        if (!isset($decoded['acknowledged']) || true !== $decoded['acknowledged']) {
            throw new AgentInstallException(sprintf('OpenSearch did not acknowledge index creation: %s — %s', $indexName, $response));
        }
    }

    private function deleteIndex(string $indexName): void
    {
        $url = sprintf('%s/%s', rtrim($this->openSearchUrl, '/'), urlencode($indexName));
        $context = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $headers = $http_response_header;

        if (false === $response && [] === $headers) {
            throw new AgentInstallException(sprintf('Failed to delete OpenSearch index: %s', $indexName));
        }

        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $header, $m)) {
                $status = (int) $m[1];
                break;
            }
        }

        if (0 === $status) {
            return;
        }

        if (!in_array($status, [200, 202, 404], true)) {
            throw new AgentInstallException(sprintf('Unexpected OpenSearch delete status %d for index %s', $status, $indexName));
        }
    }
}
