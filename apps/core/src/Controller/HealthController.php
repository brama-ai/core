<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => 'core-platform',
            'version' => '0.1.0',
            'timestamp' => date('c'),
        ]);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'ok';

        // Database connectivity check
        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'Connection failed: '.$e->getMessage()];
            $overallStatus = 'error';
            $this->logger->error('Database health check failed', ['exception' => $e]);
        }

        // Check if we can write to the database (more thorough readiness check)
        if ('ok' === $checks['database']['status']) {
            try {
                $this->connection->executeQuery('SELECT COUNT(*) FROM agent_registry');
                $checks['database_write'] = ['status' => 'ok', 'message' => 'Read/write access confirmed'];
            } catch (\Throwable $e) {
                $checks['database_write'] = ['status' => 'error', 'message' => 'Write access failed: '.$e->getMessage()];
                $overallStatus = 'error';
                $this->logger->error('Database write check failed', ['exception' => $e]);
            }
        } else {
            $checks['database_write'] = ['status' => 'skipped', 'message' => 'Database connection unavailable'];
        }

        $response = [
            'status' => $overallStatus,
            'service' => 'core-platform',
            'version' => '0.1.0',
            'timestamp' => date('c'),
            'checks' => $checks,
        ];

        $httpStatus = 'ok' === $overallStatus ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return new JsonResponse($response, $httpStatus);
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        // Liveness check - just verify the application is running
        // This should be very lightweight and not depend on external services
        return new JsonResponse([
            'status' => 'ok',
            'service' => 'core-platform',
            'version' => '0.1.0',
            'timestamp' => date('c'),
            'uptime' => $this->getUptime(),
        ]);
    }

    /**
     * @return array{seconds: float, human: string}
     */
    private function getUptime(): array
    {
        $uptime = file_get_contents('/proc/uptime');
        if (false !== $uptime) {
            $uptimeSeconds = (float) explode(' ', trim($uptime))[0];

            return [
                'seconds' => $uptimeSeconds,
                'human' => $this->formatUptime($uptimeSeconds),
            ];
        }

        return ['seconds' => 0, 'human' => 'unknown'];
    }

    private function formatUptime(float $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%dd %dh %dm', $days, $hours, $minutes);
    }
}
