<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'knowledge-agent',
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
            $checks['database'] = ['status' => 'error', 'message' => 'Connection failed: ' . $e->getMessage()];
            $overallStatus = 'error';
            $this->logger->error('Database health check failed', ['exception' => $e]);
        }

        // Check OpenSearch connectivity (knowledge agent depends on search)
        $checks['opensearch'] = $this->checkOpenSearchConnectivity();
        if ('ok' !== $checks['opensearch']['status']) {
            $overallStatus = 'error';
        }

        // Check core platform connectivity
        $checks['core_platform'] = $this->checkCoreConnectivity();
        if ('ok' !== $checks['core_platform']['status']) {
            $overallStatus = 'error';
        }

        $response = [
            'status' => $overallStatus,
            'service' => 'knowledge-agent',
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
        return $this->json([
            'status' => 'ok',
            'service' => 'knowledge-agent',
            'version' => '0.1.0',
            'timestamp' => date('c'),
        ]);
    }

    private function checkOpenSearchConnectivity(): array
    {
        $opensearchUrl = $_ENV['OPENSEARCH_URL'] ?? 'http://opensearch:9200';
        
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 3,
                    'ignore_errors' => true,
                ],
            ]);

            $result = @file_get_contents($opensearchUrl . '/_cluster/health', false, $context);
            
            if (false !== $result) {
                $health = json_decode($result, true);
                if (is_array($health) && isset($health['status'])) {
                    $status = $health['status'];
                    if ('green' === $status || 'yellow' === $status) {
                        return ['status' => 'ok', 'message' => "OpenSearch cluster status: {$status}"];
                    }
                    return ['status' => 'error', 'message' => "OpenSearch cluster status: {$status}"];
                }
            }
            
            return ['status' => 'error', 'message' => 'OpenSearch unreachable'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'OpenSearch connectivity check failed: ' . $e->getMessage()];
        }
    }

    private function checkCoreConnectivity(): array
    {
        $coreHost = $_ENV['CORE_PLATFORM_URL'] ?? 'http://core';
        
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 2,
                    'ignore_errors' => true,
                ],
            ]);

            $result = @file_get_contents($coreHost . '/health', false, $context);
            
            if (false !== $result) {
                return ['status' => 'ok', 'message' => 'Core platform reachable'];
            }
            
            return ['status' => 'error', 'message' => 'Core platform unreachable'];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Core connectivity check failed: ' . $e->getMessage()];
        }
    }
}
