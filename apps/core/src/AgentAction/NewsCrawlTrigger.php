<?php

declare(strict_types=1);

namespace App\AgentAction;

final class NewsCrawlTrigger
{
    public function __construct(
        private readonly string $internalToken,
    ) {
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function trigger(array $manifest): void
    {
        $baseUrl = $this->resolveBaseUrl($manifest);

        if (null === $baseUrl) {
            throw new AgentActionException('Cannot resolve agent base URL from manifest.');
        }

        $url = rtrim($baseUrl, '/').'/admin/trigger/crawl';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => sprintf("X-Platform-Internal-Token: %s\r\nContent-Type: application/x-www-form-urlencoded", $this->internalToken),
                'content' => '',
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if (false === $response) {
            throw new AgentActionException(sprintf('Crawl trigger failed: could not reach %s', $url));
        }

        $status = $this->extractStatusCode($http_response_header);

        if (null === $status) {
            throw new AgentActionException('Crawl trigger failed: unknown response status.');
        }

        if (404 === $status) {
            throw new AgentActionException('Agent does not expose /admin/trigger/crawl endpoint.');
        }

        if ($status >= 400) {
            $snippet = trim(substr((string) $response, 0, 180));
            throw new AgentActionException(sprintf('Crawl trigger failed with HTTP %d: %s', $status, $snippet));
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

    /**
     * @param list<string> $headers
     */
    private function extractStatusCode(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (!str_starts_with($header, 'HTTP/')) {
                continue;
            }

            $parts = explode(' ', $header);
            if (isset($parts[1]) && is_numeric($parts[1])) {
                return (int) $parts[1];
            }
        }

        return null;
    }
}
