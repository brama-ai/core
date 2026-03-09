<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2A;

use App\A2A\KnowledgeA2AHandler;
use App\Logging\TraceContext;
use App\OpenSearch\KnowledgeRepository;
use App\RabbitMQ\RabbitMQPublisher;
use App\Repository\SourceMessageRepository;
use App\Service\EmbeddingService;
use App\Service\KnowledgeTreeBuilder;
use App\Service\MessageChunker;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class KnowledgeA2AHandlerTest extends Unit
{
    private Connection&MockObject $connection;
    private KnowledgeA2AHandler $handler;

    protected function setUp(): void
    {
        $openSearchClient = $this->createMock(Client::class);
        $knowledgeRepository = new KnowledgeRepository($openSearchClient, 'knowledge_entries_v1');
        $treeBuilder = new KnowledgeTreeBuilder($knowledgeRepository);
        $chunker = new MessageChunker();
        $publisher = new RabbitMQPublisher('amqp://guest:guest@localhost:5672/');
        $httpClient = $this->createMock(HttpClientInterface::class);
        $embeddingService = new EmbeddingService($httpClient, 'http://litellm:4000', 'key', 'text-embedding-3-small', new TraceContext());
        $this->connection = $this->createMock(Connection::class);
        $sourceMessageRepository = new SourceMessageRepository($this->connection);

        $this->handler = new KnowledgeA2AHandler(
            $knowledgeRepository,
            $treeBuilder,
            $chunker,
            $publisher,
            $embeddingService,
            $sourceMessageRepository,
        );
    }

    public function testStoreMessageIntentPersistsMessage(): void
    {
        $payload = [
            'message' => [
                'chat_id' => '-100123',
                'message_id' => 'm-77',
                'text' => 'stored message',
            ],
            'metadata' => [
                'channel' => 'telegram.main',
            ],
        ];

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('INSERT INTO knowledge_source_messages'),
                $this->callback(static function (array $params): bool {
                    return 'm-77' === $params['message_id']
                        && '-100123' === $params['chat_id']
                        && 'req-store-1' === $params['request_id']
                        && 'trace-store-1' === $params['trace_id'];
                }),
            )
            ->willReturn('stored-id-1');

        $result = $this->handler->handle([
            'intent' => 'knowledge.store_message',
            'request_id' => 'req-store-1',
            'trace_id' => 'trace-store-1',
            'payload' => $payload,
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('req-store-1', $result['request_id']);
        $this->assertSame('stored-id-1', $result['result']['id']);
        $this->assertTrue($result['result']['stored']);
    }
}
