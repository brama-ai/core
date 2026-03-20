<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\TokenBucketRateLimiter;
use Doctrine\DBAL\Connection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TokenBucketRateLimiter $rateLimiter,
        private readonly string $rabbitmqUrl,
    ) {
    }

    #[Route('/health/worker', name: 'health_worker', methods: ['GET'])]
    public function worker(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => time(),
            'checks' => [],
        ];

        // Check database connectivity
        try {
            $this->connection->fetchOne('SELECT 1');
            $health['checks']['database'] = ['status' => 'healthy'];
        } catch (\Throwable $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        // Check RabbitMQ connectivity
        try {
            $connection = $this->createRabbitMQConnection();
            $channel = $connection->channel();
            
            if (null !== $channel) {
                // Check if our queue exists
                $queueInfo = $channel->queue_declare('knowledge.chunks', true);
                $health['checks']['rabbitmq'] = [
                    'status' => 'healthy',
                    'queue_messages' => $queueInfo[1] ?? 0,
                ];
                
                $channel->close();
            } else {
                throw new \RuntimeException('Failed to create RabbitMQ channel');
            }
            
            $connection->close();
        } catch (\Throwable $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['rabbitmq'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        // Check rate limiter status
        try {
            $tokenCount = $this->rateLimiter->getTokenCount('llm_calls');
            $timeUntilNext = $this->rateLimiter->getTimeUntilNextToken('llm_calls');
            
            $health['checks']['rate_limiter'] = [
                'status' => 'healthy',
                'available_tokens' => $tokenCount,
                'time_until_next_token' => $timeUntilNext,
            ];
        } catch (\Throwable $e) {
            $health['status'] = 'unhealthy';
            $health['checks']['rate_limiter'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        // Check processed chunks statistics
        try {
            $stats = $this->getProcessingStats();
            $health['checks']['processing_stats'] = [
                'status' => 'healthy',
                'stats' => $stats,
            ];
        } catch (\Throwable $e) {
            $health['checks']['processing_stats'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }

        $statusCode = $health['status'] === 'healthy' ? 200 : 503;
        
        return new JsonResponse($health, $statusCode);
    }

    #[Route('/health/dlq', name: 'health_dlq', methods: ['GET'])]
    public function dlq(): JsonResponse
    {
        try {
            $connection = $this->createRabbitMQConnection();
            $channel = $connection->channel();
            
            if (null === $channel) {
                throw new \RuntimeException('Failed to create RabbitMQ channel');
            }
            
            // Check dead letter queue
            $dlqInfo = $channel->queue_declare('knowledge.dlq', true);
            $messageCount = $dlqInfo[1] ?? 0;
            
            $channel->close();
            $connection->close();
            
            return new JsonResponse([
                'status' => 'healthy',
                'dlq_message_count' => $messageCount,
                'timestamp' => time(),
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => time(),
            ], 503);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getProcessingStats(): array
    {
        $totalProcessed = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM processed_chunks WHERE status = 'completed'"
        );

        $totalFailed = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM processed_chunks WHERE status = 'processing' AND attempt_count >= 3"
        );

        $recentlyProcessed = $this->connection->fetchOne(
            "SELECT COUNT(*) FROM processed_chunks WHERE status = 'completed' AND processed_at > NOW() - INTERVAL '1 hour'"
        );

        $avgAttempts = $this->connection->fetchOne(
            "SELECT AVG(attempt_count) FROM processed_chunks WHERE status = 'completed'"
        );

        return [
            'total_completed' => (int) ($totalProcessed ?: 0),
            'total_failed' => (int) ($totalFailed ?: 0),
            'completed_last_hour' => (int) ($recentlyProcessed ?: 0),
            'average_attempts' => round((float) ($avgAttempts ?: 0), 2),
        ];
    }

    private function createRabbitMQConnection(): AMQPStreamConnection
    {
        $parsed = parse_url($this->rabbitmqUrl);
        \assert(false !== $parsed);

        return new AMQPStreamConnection(
            host: $parsed['host'] ?? 'rabbitmq',
            port: (int) ($parsed['port'] ?? 5672),
            user: urldecode($parsed['user'] ?? 'guest'),
            password: urldecode($parsed['pass'] ?? 'guest'),
            vhost: urldecode(ltrim($parsed['path'] ?? '/', '/')) ?: '/',
        );
    }
}