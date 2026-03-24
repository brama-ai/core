<?php

declare(strict_types=1);

namespace App\AgentRegistry;

final class AgentHealthChecker
{
    private const DEFAULT_TIMEOUT = 5;
    private const INLINE_TIMEOUT = 2;

    /**
     * Check agent health endpoint. Returns true if the endpoint responds with HTTP 2xx.
     * Uses a short timeout suitable for inline probes during registration.
     */
    public function checkInline(string $url): bool
    {
        return $this->doCheck($url, self::INLINE_TIMEOUT);
    }

    /**
     * Check agent health endpoint. Returns true if the endpoint responds with HTTP 2xx.
     * Uses the standard poller timeout.
     */
    public function check(string $url): bool
    {
        return $this->doCheck($url, self::DEFAULT_TIMEOUT);
    }

    private function doCheck(string $url, int $timeout): bool
    {
        if ('' === $url) {
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static fn (): bool => true);

        try {
            $result = file_get_contents($url, false, $context);
            $responseHeaders = $http_response_header;
        } finally {
            restore_error_handler();
        }

        if (false === $result) {
            return false;
        }

        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+ (\d+)#', $header, $m)) {
                $code = (int) $m[1];

                return $code >= 200 && $code < 300;
            }
        }

        return true;
    }
}
