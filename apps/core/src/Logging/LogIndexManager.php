<?php

declare(strict_types=1);

namespace App\Logging;

final class LogIndexManager
{
    private const INDEX_PREFIX = 'platform_logs';
    private const TEMPLATE_NAME = 'platform_logs_template';

    public function __construct(
        private readonly string $opensearchUrl,
    ) {
    }

    public function setupTemplate(): bool
    {
        $url = sprintf('%s/_index_template/%s', $this->baseUrl(), self::TEMPLATE_NAME);

        $body = json_encode([
            'index_patterns' => [self::INDEX_PREFIX.'_*'],
            'template' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'index' => ['refresh_interval' => '5s'],
                ],
                'mappings' => [
                    'properties' => [
                        '@timestamp' => ['type' => 'date', 'format' => 'strict_date_time||epoch_millis'],
                        'level' => ['type' => 'keyword'],
                        'level_name' => ['type' => 'keyword'],
                        'message' => ['type' => 'text'],
                        'channel' => ['type' => 'keyword'],
                        'app_name' => ['type' => 'keyword'],
                        'trace_id' => ['type' => 'keyword'],
                        'request_id' => ['type' => 'keyword'],
                        'request_uri' => ['type' => 'keyword'],
                        'request_method' => ['type' => 'keyword'],
                        'client_ip' => ['type' => 'ip'],
                        'username' => ['type' => 'keyword'],
                        'context' => ['type' => 'object', 'enabled' => false],
                        'extra' => ['type' => 'object', 'enabled' => false],
                        'exception' => [
                            'type' => 'object',
                            'properties' => [
                                'class' => ['type' => 'keyword'],
                                'message' => ['type' => 'text'],
                                'trace' => ['type' => 'text', 'index' => false],
                            ],
                        ],
                    ],
                ],
            ],
            'priority' => 100,
        ], JSON_THROW_ON_ERROR);

        return $this->httpRequest('PUT', $url, $body);
    }

    public function ensureTodayIndex(): bool
    {
        $indexName = $this->todayIndexName();

        if ($this->indexExists($indexName)) {
            return true;
        }

        return $this->httpRequest('PUT', sprintf('%s/%s', $this->baseUrl(), $indexName), json_encode((object) [], JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<array{index: string, size_bytes: int, date: string}>
     */
    public function listLogIndices(): array
    {
        $url = sprintf('%s/_cat/indices/%s_*?format=json&h=index,store.size&bytes=b', $this->baseUrl(), self::INDEX_PREFIX);

        $response = $this->httpGet($url);
        if (null === $response) {
            return [];
        }

        /** @var list<array{index: string, 'store.size': string}> $indices */
        $indices = json_decode($response, true);

        $result = [];
        foreach ($indices as $entry) {
            $indexName = $entry['index'];
            $dateStr = $this->extractDateFromIndex($indexName);
            if (null === $dateStr) {
                continue;
            }

            $result[] = [
                'index' => $indexName,
                'size_bytes' => (int) $entry['store.size'],
                'date' => $dateStr,
            ];
        }

        usort($result, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return $result;
    }

    public function deleteIndex(string $indexName): bool
    {
        return $this->httpRequest('DELETE', sprintf('%s/%s', $this->baseUrl(), $indexName));
    }

    /**
     * @param array<string, mixed> $searchBody
     *
     * @return array<string, mixed>|null
     */
    public function search(array $searchBody): ?array
    {
        $url = sprintf('%s/%s_*/_search', $this->baseUrl(), self::INDEX_PREFIX);

        $response = $this->httpGet($url, json_encode($searchBody, JSON_THROW_ON_ERROR));
        if (null === $response) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response, true);

        return $decoded;
    }

    private function todayIndexName(): string
    {
        return sprintf('%s_%s', self::INDEX_PREFIX, date('Y_m_d'));
    }

    private function indexExists(string $indexName): bool
    {
        $url = sprintf('%s/%s', $this->baseUrl(), $indexName);

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

    private function httpRequest(string $method, string $url, ?string $body = null): bool
    {
        $options = [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ];

        if (null !== $body) {
            $options['content'] = $body;
        }

        $context = stream_context_create(['http' => $options]);
        $response = @file_get_contents($url, false, $context);

        if (false === $response) {
            return false;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($response, true);

        return isset($decoded['acknowledged']) && true === $decoded['acknowledged'];
    }

    private function httpGet(string $url, ?string $body = null): ?string
    {
        $options = [
            'method' => null !== $body ? 'POST' : 'GET',
            'header' => "Content-Type: application/json\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ];

        if (null !== $body) {
            $options['content'] = $body;
        }

        $context = stream_context_create(['http' => $options]);
        $response = @file_get_contents($url, false, $context);

        return false === $response ? null : $response;
    }

    private function baseUrl(): string
    {
        return rtrim($this->opensearchUrl, '/');
    }

    private function extractDateFromIndex(string $indexName): ?string
    {
        $prefix = self::INDEX_PREFIX.'_';
        if (!str_starts_with($indexName, $prefix)) {
            return null;
        }

        $datePart = substr($indexName, \strlen($prefix));

        if (1 !== preg_match('/^\d{4}_\d{2}_\d{2}$/', $datePart)) {
            return null;
        }

        return str_replace('_', '-', $datePart);
    }
}
