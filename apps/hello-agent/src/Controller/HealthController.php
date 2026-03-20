<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'hello-agent',
            'version' => '0.1.0',
            'timestamp' => date('c'),
        ]);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'ok';

        // Check core platform connectivity (this agent depends on core)
        $checks['core_platform'] = $this->checkCoreConnectivity();
        if ('ok' !== $checks['core_platform']['status']) {
            $overallStatus = 'error';
        }

        $response = [
            'status' => $overallStatus,
            'service' => 'hello-agent',
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
            'service' => 'hello-agent',
            'version' => '0.1.0',
            'timestamp' => date('c'),
        ]);
    }

    private function checkCoreConnectivity(): array
    {
        // In a real deployment, this would check connectivity to the core platform
        // For now, we'll assume it's available if we can resolve the hostname
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
