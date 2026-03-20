<?php

declare(strict_types=1);

namespace App\Tests\Functional\Worker;

use App\Command\KnowledgeWorkerCommand;
use App\OpenSearch\KnowledgeRepository;
use App\Service\EmbeddingService;
use App\Service\TokenBucketRateLimiter;
use App\Workflow\KnowledgeExtractionAgent;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Functional tests for KnowledgeWorkerCommand.
 * Tests worker processing, deduplication, retry logic, and DLQ behavior.
 */
final class KnowledgeWorkerTest extends KernelTestCase
{
    private Connection $dbal;
    private KnowledgeWorkerCommand $command;
    private CommandTester $commandTester;
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel(['environment' => 'test']);

        $container = static::getContainer();
        $this->dbal = $container->get(Connection::class);

        // Clean up test data
        $this->dbal->executeStatement('DELETE FROM processed_chunks');
        $this->dbal->executeStatement('DELETE FROM rate_limiter_buckets');

        // Create worker command with test dependencies
        $this->command = new KnowledgeWorkerCommand(
            rabbitmqUrl: $_ENV['RABBITMQ_URL'] ?? 'amqp://guest:guest@rabbitmq:5672/',
            agent: $container->get(KnowledgeExtractionAgent::class),
            knowledgeRepository: $container->get(KnowledgeRepository::class),
            embeddingService: $container->get(EmbeddingService::class),
            dbal: $this->dbal,
            rateLimiter: $container->get(TokenBucketRateLimiter::class),
            concurrency: 1
        );

        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);

        // Set up RabbitMQ connection for testing
        $this->setupRabbitMQ();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->dbal->executeStatement('DELETE FROM processed_chunks');
        $this->dbal->executeStatement('DELETE FROM rate_limiter_buckets');

        // Clean up RabbitMQ queues
        $this->cleanupRabbitMQ();

        parent::tearDown();
    }

    public function testWorkerProcessesValidChunk(): void
    {
        // Arrange: Create a test chunk with valid messages
        $chunkHash = 'test_chunk_' . uniqid();
        $chunk = [
            'chunk_hash' => $chunkHash,
            'messages' => [
                [
                    'id' => 1,
                    'text' => 'This is valuable knowledge about PHP testing.',
                    'timestamp' => time(),
                    'user_id' => 123,
                    'username' => 'testuser'
                ]
            ],
            'meta' => [
                'source' => 'telegram',
                'chat_id' => 456
            ]
        ];

        // Publish chunk to queue
        $this->publishChunk($chunk);

        // Act: Process the message (simulate single message processing)
        $this->processOneMessage();

        // Assert: Check that chunk was processed and marked complete
        $status = $this->dbal->fetchOne(
            "SELECT status FROM processed_chunks WHERE chunk_hash = ?",
            [$chunkHash]
        );

        $this->assertEquals('completed', $status);
    }

    public function testWorkerSkipsDuplicateChunks(): void
    {
        // Arrange: Mark chunk as already completed
        $chunkHash = 'duplicate_chunk_' . uniqid();
        $this->dbal->executeStatement(
            "INSERT INTO processed_chunks (chunk_hash, status, attempt_count, created_at, processed_at) 
             VALUES (?, 'completed', 1, now(), now())",
            [$chunkHash]
        );

        $chunk = [
            'chunk_hash' => $chunkHash,
            'messages' => [
                [
                    'id' => 1,
                    'text' => 'This should be skipped.',
                    'timestamp' => time(),
                    'user_id' => 123,
                    'username' => 'testuser'
                ]
            ],
            'meta' => ['source' => 'telegram']
        ];

        // Publish duplicate chunk
        $this->publishChunk($chunk);

        // Act: Process the message
        $this->processOneMessage();

        // Assert: Attempt count should remain 1 (not incremented)
        $attemptCount = $this->dbal->fetchOne(
            "SELECT attempt_count FROM processed_chunks WHERE chunk_hash = ?",
            [$chunkHash]
        );

        $this->assertEquals(1, $attemptCount);
    }

    public function testWorkerRetryLogic(): void
    {
        // Arrange: Create chunk that will fail processing
        $chunkHash = 'retry_chunk_' . uniqid();
        $chunk = [
            'chunk_hash' => $chunkHash,
            'messages' => [
                [
                    'id' => 1,
                    'text' => 'Invalid message that will cause processing to fail',
                    'timestamp' => time(),
                    'user_id' => 123,
                    'username' => 'testuser'
                ]
            ],
            'meta' => ['source' => 'telegram']
        ];

        // Publish chunk multiple times to simulate retries
        for ($i = 0; $i < 4; $i++) {
            $this->publishChunk($chunk);
        }

        // Act: Process messages (simulate failures)
        for ($i = 0; $i < 3; $i++) {
            $this->processOneMessage();
        }

        // Assert: Check attempt count reaches max retries
        $attemptCount = $this->dbal->fetchOne(
            "SELECT attempt_count FROM processed_chunks WHERE chunk_hash = ?",
            [$chunkHash]
        );

        $this->assertEquals(3, $attemptCount);

        // Process one more time - should go to DLQ
        $this->processOneMessage();

        // Check that message is in DLQ (by checking queue message count)
        $dlqCount = $this->getQueueMessageCount('knowledge.dlq');
        $this->assertGreaterThan(0, $dlqCount);
    }

    public function testWorkerRateLimiting(): void
    {
        // Arrange: Set up rate limiter with very low limit
        $rateLimiter = static::getContainer()->get(TokenBucketRateLimiter::class);
        
        // Consume all available tokens
        while ($rateLimiter->consume('llm_calls', 1)) {
            // Keep consuming until rate limited
        }

        $chunkHash = 'rate_limited_chunk_' . uniqid();
        $chunk = [
            'chunk_hash' => $chunkHash,
            'messages' => [
                [
                    'id' => 1,
                    'text' => 'This should be rate limited.',
                    'timestamp' => time(),
                    'user_id' => 123,
                    'username' => 'testuser'
                ]
            ],
            'meta' => ['source' => 'telegram']
        ];

        $this->publishChunk($chunk);

        // Act: Try to process - should be requeued due to rate limiting
        $this->processOneMessage();

        // Assert: Message should be requeued (not processed)
        $status = $this->dbal->fetchOne(
            "SELECT status FROM processed_chunks WHERE chunk_hash = ?",
            [$chunkHash]
        );

        // Should be 'processing' (not 'completed') due to rate limiting
        $this->assertEquals('processing', $status);
    }

    public function testWorkerDLQBehavior(): void
    {
        // Arrange: Create chunk that exceeds max retries
        $chunkHash = 'dlq_chunk_' . uniqid();
        
        // Pre-populate with max retries
        $this->dbal->executeStatement(
            "INSERT INTO processed_chunks (chunk_hash, status, attempt_count, created_at) 
             VALUES (?, 'processing', 3, now())",
            [$chunkHash]
        );

        $chunk = [
            'chunk_hash' => $chunkHash,
            'messages' => [
                [
                    'id' => 1,
                    'text' => 'This should go to DLQ.',
                    'timestamp' => time(),
                    'user_id' => 123,
                    'username' => 'testuser'
                ]
            ],
            'meta' => ['source' => 'telegram']
        ];

        $this->publishChunk($chunk);

        // Act: Process message - should go to DLQ
        $this->processOneMessage();

        // Assert: Check DLQ has the message
        $dlqCount = $this->getQueueMessageCount('knowledge.dlq');
        $this->assertGreaterThan(0, $dlqCount);
    }

    private function setupRabbitMQ(): void
    {
        $rabbitmqUrl = $_ENV['RABBITMQ_URL'] ?? 'amqp://guest:guest@rabbitmq:5672/';
        $parsed = parse_url($rabbitmqUrl);

        $this->connection = new AMQPStreamConnection(
            host: $parsed['host'] ?? 'rabbitmq',
            port: (int) ($parsed['port'] ?? 5672),
            user: urldecode($parsed['user'] ?? 'guest'),
            password: urldecode($parsed['pass'] ?? 'guest'),
            vhost: urldecode(ltrim($parsed['path'] ?? '/', '/')) ?: '/',
        );

        $this->channel = $this->connection->channel();

        // Declare test topology
        $this->channel->exchange_declare('knowledge.dlx', 'direct', false, true, false);
        $this->channel->queue_declare('knowledge.dlq', false, true, false, false);
        $this->channel->queue_bind('knowledge.dlq', 'knowledge.dlx', 'knowledge.dlq');

        $this->channel->exchange_declare('knowledge.direct', 'direct', false, true, false);
        $this->channel->queue_declare('knowledge.chunks', false, true, false, false, false, [
            'x-dead-letter-exchange' => ['S', 'knowledge.dlx'],
            'x-dead-letter-routing-key' => ['S', 'knowledge.dlq'],
        ]);
        $this->channel->queue_bind('knowledge.chunks', 'knowledge.direct', 'knowledge.chunks');
    }

    private function cleanupRabbitMQ(): void
    {
        if (isset($this->channel)) {
            // Purge test queues
            $this->channel->queue_purge('knowledge.chunks');
            $this->channel->queue_purge('knowledge.dlq');
            $this->channel->close();
        }

        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    private function publishChunk(array $chunk): void
    {
        $message = new AMQPMessage(
            json_encode($chunk, JSON_THROW_ON_ERROR),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );

        $this->channel->basic_publish($message, 'knowledge.direct', 'knowledge.chunks');
    }

    private function processOneMessage(): void
    {
        // Get one message from queue and process it
        $message = $this->channel->basic_get('knowledge.chunks');
        
        if ($message) {
            // Simulate message processing by calling the worker's processMessage method
            // Since processMessage is private, we'll use reflection to access it
            $reflection = new \ReflectionClass($this->command);
            $method = $reflection->getMethod('processMessage');
            $method->setAccessible(true);
            
            $io = new \Symfony\Component\Console\Style\SymfonyStyle(
                new \Symfony\Component\Console\Input\ArrayInput([]),
                new \Symfony\Component\Console\Output\NullOutput()
            );
            
            try {
                $method->invoke($this->command, $message, $io);
            } catch (\Throwable $e) {
                // Handle processing errors
                $message->nack(false, true);
            }
        }
    }

    private function getQueueMessageCount(string $queueName): int
    {
        $queueInfo = $this->channel->queue_declare($queueName, true);
        return $queueInfo[1] ?? 0; // Message count is at index 1
    }
}