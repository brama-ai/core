<?php

declare(strict_types=1);

namespace App\Tests\Integration\RabbitMQ;

use App\RabbitMQ\RabbitMQPublisher;
use Codeception\Test\Unit;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

final class RabbitMQPublisherIntegrationTest extends Unit
{
    private RabbitMQPublisher $publisher;
    private string $rabbitmqUrl;

    protected function setUp(): void
    {
        parent::setUp();

        // Use test RabbitMQ configuration
        $this->rabbitmqUrl = $_ENV['RABBITMQ_URL'] ?? 'amqp://app:app@rabbitmq:5672/test';
        $this->publisher = new RabbitMQPublisher($this->rabbitmqUrl);
    }

    protected function tearDown(): void
    {
        // Clean up test queues
        $this->purgeTestQueues();
        parent::tearDown();
    }

    public function testPublishChunkCreatesMessageInQueue(): void
    {
        $testChunk = [
            'chunk_hash' => 'test_hash_123',
            'messages' => [
                [
                    'id' => 'msg_1',
                    'text' => 'Test message content',
                    'timestamp' => '2024-03-19T10:00:00Z',
                ],
            ],
        ];

        // Publish the chunk
        $this->publisher->publishChunk($testChunk);

        // Verify message is in queue
        $connection = $this->createTestConnection();
        $channel = $connection->channel();
        
        // Check queue exists and has messages
        [$queueName, $messageCount] = $channel->queue_declare('knowledge.chunks', true);
        $this->assertGreaterThan(0, $messageCount, 'Queue should contain at least one message');

        // Consume and verify message content
        $receivedMessage = $channel->basic_get('knowledge.chunks');
        $this->assertNotNull($receivedMessage, 'Should receive a message from queue');

        $decodedBody = json_decode($receivedMessage->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertEquals($testChunk, $decodedBody, 'Message body should match published chunk');

        // Acknowledge the message to clean up
        $channel->basic_ack($receivedMessage->getDeliveryTag());
        
        $channel->close();
        $connection->close();
    }

    public function testTopologyIsCreatedCorrectly(): void
    {
        // Trigger topology creation by publishing a message
        $this->publisher->publishChunk(['test' => 'data']);

        $connection = $this->createTestConnection();
        $channel = $connection->channel();

        // Verify main exchange exists
        try {
            $channel->exchange_declare('knowledge.direct', 'direct', true);
            $this->assertTrue(true, 'Main exchange should exist');
        } catch (\Exception $e) {
            $this->fail('Main exchange should exist: ' . $e->getMessage());
        }

        // Verify DLX exists
        try {
            $channel->exchange_declare('knowledge.dlx', 'direct', true);
            $this->assertTrue(true, 'Dead letter exchange should exist');
        } catch (\Exception $e) {
            $this->fail('Dead letter exchange should exist: ' . $e->getMessage());
        }

        // Verify main queue exists
        try {
            [$queueName, $messageCount] = $channel->queue_declare('knowledge.chunks', true);
            $this->assertEquals('knowledge.chunks', $queueName);
        } catch (\Exception $e) {
            $this->fail('Main queue should exist: ' . $e->getMessage());
        }

        // Verify DLQ exists
        try {
            [$queueName, $messageCount] = $channel->queue_declare('knowledge.dlq', true);
            $this->assertEquals('knowledge.dlq', $queueName);
        } catch (\Exception $e) {
            $this->fail('Dead letter queue should exist: ' . $e->getMessage());
        }

        $channel->close();
        $connection->close();
    }

    public function testGetDlqCountReturnsCorrectCount(): void
    {
        // Initially DLQ should be empty
        $initialCount = $this->publisher->getDlqCount();
        $this->assertEquals(0, $initialCount, 'DLQ should initially be empty');

        // Manually add a message to DLQ for testing
        $connection = $this->createTestConnection();
        $channel = $connection->channel();
        
        // Ensure topology exists
        $this->publisher->publishChunk(['test' => 'setup']);
        
        // Manually publish to DLQ
        $testMessage = json_encode(['test' => 'dlq_message'], JSON_THROW_ON_ERROR);
        $channel->basic_publish(
            new \PhpAmqpLib\Message\AMQPMessage($testMessage),
            'knowledge.dlx',
            'knowledge.dlq'
        );

        $channel->close();
        $connection->close();

        // Check DLQ count
        $count = $this->publisher->getDlqCount();
        $this->assertEquals(1, $count, 'DLQ should contain one message');
    }

    public function testRequeueDlqMovesMessagesBackToMainQueue(): void
    {
        // Setup: add a message to DLQ
        $connection = $this->createTestConnection();
        $channel = $connection->channel();
        
        // Ensure topology exists
        $this->publisher->publishChunk(['test' => 'setup']);
        
        $testMessage = json_encode(['test' => 'requeue_test'], JSON_THROW_ON_ERROR);
        $channel->basic_publish(
            new \PhpAmqpLib\Message\AMQPMessage($testMessage),
            'knowledge.dlx',
            'knowledge.dlq'
        );

        $channel->close();
        $connection->close();

        // Verify message is in DLQ
        $dlqCount = $this->publisher->getDlqCount();
        $this->assertEquals(1, $dlqCount, 'DLQ should contain one message before requeue');

        // Requeue messages
        $requeuedCount = $this->publisher->requeueDlq();
        $this->assertEquals(1, $requeuedCount, 'Should requeue one message');

        // Verify DLQ is now empty
        $dlqCountAfter = $this->publisher->getDlqCount();
        $this->assertEquals(0, $dlqCountAfter, 'DLQ should be empty after requeue');

        // Verify message is back in main queue
        $connection = $this->createTestConnection();
        $channel = $connection->channel();
        
        [$queueName, $messageCount] = $channel->queue_declare('knowledge.chunks', true);
        $this->assertGreaterThan(0, $messageCount, 'Main queue should contain requeued message');

        $channel->close();
        $connection->close();
    }

    public function testMultipleChunksArePublishedCorrectly(): void
    {
        $chunks = [
            ['chunk_hash' => 'hash_1', 'messages' => [['id' => 'msg_1']]],
            ['chunk_hash' => 'hash_2', 'messages' => [['id' => 'msg_2']]],
            ['chunk_hash' => 'hash_3', 'messages' => [['id' => 'msg_3']]],
        ];

        // Publish all chunks
        foreach ($chunks as $chunk) {
            $this->publisher->publishChunk($chunk);
        }

        // Verify all messages are in queue
        $connection = $this->createTestConnection();
        $channel = $connection->channel();
        
        [$queueName, $messageCount] = $channel->queue_declare('knowledge.chunks', true);
        $this->assertEquals(3, $messageCount, 'Queue should contain all published messages');

        // Consume and verify each message
        $receivedChunks = [];
        for ($i = 0; $i < 3; $i++) {
            $message = $channel->basic_get('knowledge.chunks');
            $this->assertNotNull($message, "Should receive message $i");
            
            $decodedBody = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $receivedChunks[] = $decodedBody;
            
            $channel->basic_ack($message->getDeliveryTag());
        }

        // Verify all chunks were received (order may vary)
        $this->assertCount(3, $receivedChunks);
        foreach ($chunks as $originalChunk) {
            $this->assertContains($originalChunk, $receivedChunks);
        }

        $channel->close();
        $connection->close();
    }

    private function createTestConnection(): AMQPStreamConnection
    {
        $parsed = parse_url($this->rabbitmqUrl);
        
        return new AMQPStreamConnection(
            host: $parsed['host'] ?? 'rabbitmq',
            port: (int) ($parsed['port'] ?? 5672),
            user: urldecode($parsed['user'] ?? 'guest'),
            password: urldecode($parsed['pass'] ?? 'guest'),
            vhost: urldecode(ltrim($parsed['path'] ?? '/', '/')) ?: '/',
        );
    }

    private function purgeTestQueues(): void
    {
        try {
            $connection = $this->createTestConnection();
            $channel = $connection->channel();

            // Purge test queues
            $channel->queue_purge('knowledge.chunks');
            $channel->queue_purge('knowledge.dlq');

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
}