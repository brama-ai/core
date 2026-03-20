<?php

declare(strict_types=1);

namespace App\Tests\Integration\A2A;

use App\A2A\KnowledgeA2AHandler;
use App\OpenSearch\KnowledgeRepository;
use App\RabbitMQ\RabbitMQPublisher;
use App\Repository\SourceMessageRepository;
use App\Service\EmbeddingService;
use App\Service\KnowledgeTreeBuilder;
use App\Service\MessageChunker;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class KnowledgeA2AIntegrationTest extends Unit
{
    private KnowledgeRepository&MockObject $repository;
    private KnowledgeTreeBuilder&MockObject $treeBuilder;
    private MessageChunker&MockObject $chunker;
    private RabbitMQPublisher&MockObject $publisher;
    private EmbeddingService&MockObject $embeddingService;
    private SourceMessageRepository&MockObject $sourceMessageRepository;
    private KnowledgeA2AHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(KnowledgeRepository::class);
        $this->treeBuilder = $this->createMock(KnowledgeTreeBuilder::class);
        $this->chunker = $this->createMock(MessageChunker::class);
        $this->publisher = $this->createMock(RabbitMQPublisher::class);
        $this->embeddingService = $this->createMock(EmbeddingService::class);
        $this->sourceMessageRepository = $this->createMock(SourceMessageRepository::class);

        $this->handler = new KnowledgeA2AHandler(
            $this->repository,
            $this->treeBuilder,
            $this->chunker,
            $this->publisher,
            $this->embeddingService,
            $this->sourceMessageRepository
        );
    }

    public function testHandleSearchKnowledgeIntent(): void
    {
        $request = [
            'intent' => 'search_knowledge',
            'request_id' => 'test-search-123',
            'payload' => [
                'query' => 'Symfony cache',
                'mode' => 'hybrid',
                'size' => 5,
            ],
        ];

        $mockResults = [
            [
                'id' => 'entry-1',
                'title' => 'Symfony Cache Configuration',
                'body' => 'How to configure Symfony cache',
                'tags' => ['symfony', 'cache'],
                'category' => 'Technology',
            ],
        ];

        $this->embeddingService
            ->expects($this->once())
            ->method('embed')
            ->with('Symfony cache')
            ->willReturn([0.1, 0.2, 0.3]);

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(
                'Symfony cache',
                'hybrid',
                ['size' => 5, 'embedding' => [0.1, 0.2, 0.3]]
            )
            ->willReturn($mockResults);

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'completed',
            'request_id' => 'test-search-123',
            'result' => [
                'query' => 'Symfony cache',
                'mode' => 'hybrid',
                'total' => 1,
                'entries' => $mockResults,
            ],
        ], $result);
    }

    public function testHandleSearchWithKeywordMode(): void
    {
        $request = [
            'intent' => 'knowledge.search',
            'request_id' => 'test-keyword-search',
            'payload' => [
                'query' => 'Docker deployment',
                'mode' => 'keyword',
                'size' => 10,
            ],
        ];

        $mockResults = [];

        // Embedding should not be called for keyword mode
        $this->embeddingService
            ->expects($this->never())
            ->method('embed');

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(
                'Docker deployment',
                'keyword',
                ['size' => 10]
            )
            ->willReturn($mockResults);

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'completed',
            'request_id' => 'test-keyword-search',
            'result' => [
                'query' => 'Docker deployment',
                'mode' => 'keyword',
                'total' => 0,
                'entries' => [],
            ],
        ], $result);
    }

    public function testHandleSearchWithMissingQuery(): void
    {
        $request = [
            'intent' => 'search_knowledge',
            'request_id' => 'test-missing-query',
            'payload' => [
                'mode' => 'hybrid',
            ],
        ];

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'failed',
            'request_id' => 'test-missing-query',
            'error' => 'query is required',
        ], $result);
    }

    public function testHandleExtractFromMessages(): void
    {
        $request = [
            'intent' => 'extract_from_messages',
            'request_id' => 'test-extract-456',
            'payload' => [
                'messages' => [
                    ['id' => 'msg1', 'text' => 'How to configure Symfony?'],
                    ['id' => 'msg2', 'text' => 'Use config/packages/cache.yaml'],
                ],
                'meta' => [
                    'chat_id' => '123456',
                    'created_by' => 'user123',
                ],
            ],
        ];

        $mockChunks = [
            [
                'chunk_hash' => 'hash123',
                'messages' => [
                    ['id' => 'msg1', 'text' => 'How to configure Symfony?'],
                    ['id' => 'msg2', 'text' => 'Use config/packages/cache.yaml'],
                ],
            ],
        ];

        $this->chunker
            ->expects($this->once())
            ->method('chunk')
            ->with($request['payload']['messages'])
            ->willReturn($mockChunks);

        $this->publisher
            ->expects($this->once())
            ->method('publishChunk')
            ->with([
                'chunk_hash' => 'hash123',
                'messages' => [
                    ['id' => 'msg1', 'text' => 'How to configure Symfony?'],
                    ['id' => 'msg2', 'text' => 'Use config/packages/cache.yaml'],
                ],
                'meta' => [
                    'chat_id' => '123456',
                    'created_by' => 'user123',
                ],
            ]);

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'queued',
            'request_id' => 'test-extract-456',
            'result' => ['chunks_queued' => 1],
        ], $result);
    }

    public function testHandleExtractWithEmptyMessages(): void
    {
        $request = [
            'intent' => 'knowledge.upload',
            'request_id' => 'test-empty-messages',
            'payload' => [
                'messages' => [],
            ],
        ];

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'failed',
            'request_id' => 'test-empty-messages',
            'error' => 'messages array is required',
        ], $result);
    }

    public function testHandleGetTree(): void
    {
        $request = [
            'intent' => 'get_tree',
            'request_id' => 'test-tree-789',
            'payload' => [],
        ];

        $mockTree = [
            ['path' => 'Technology', 'count' => 10],
            ['path' => 'Technology/PHP', 'count' => 5],
            ['path' => 'Technology/PHP/Symfony', 'count' => 3],
        ];

        $this->treeBuilder
            ->expects($this->once())
            ->method('build')
            ->willReturn($mockTree);

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'completed',
            'request_id' => 'test-tree-789',
            'result' => ['tree' => $mockTree],
        ], $result);
    }

    public function testHandleStoreMessage(): void
    {
        $request = [
            'intent' => 'knowledge.store_message',
            'request_id' => 'test-store-message',
            'trace_id' => 'trace-123',
            'payload' => [
                'id' => 'msg-456',
                'text' => 'Test message content',
                'from' => 'user123',
                'timestamp' => '2024-03-19T10:00:00Z',
            ],
        ];

        $this->sourceMessageRepository
            ->expects($this->once())
            ->method('upsert')
            ->with(
                $request['payload'],
                'test-store-message',
                'trace-123'
            )
            ->willReturn('stored-id-789');

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'completed',
            'request_id' => 'test-store-message',
            'result' => [
                'stored' => true,
                'id' => 'stored-id-789',
            ],
        ], $result);
    }

    public function testHandleStoreMessageWithEmptyPayload(): void
    {
        $request = [
            'intent' => 'store_message',
            'request_id' => 'test-empty-store',
            'payload' => [],
        ];

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'failed',
            'request_id' => 'test-empty-store',
            'error' => 'message payload is required',
        ], $result);
    }

    public function testHandleUnknownIntent(): void
    {
        $request = [
            'intent' => 'unknown_intent',
            'request_id' => 'test-unknown',
            'payload' => [],
        ];

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'failed',
            'request_id' => 'test-unknown',
            'error' => 'Unknown intent: unknown_intent',
        ], $result);
    }

    public function testHandleRequestWithoutRequestId(): void
    {
        $request = [
            'intent' => 'get_tree',
            'payload' => [],
        ];

        $mockTree = [];

        $this->treeBuilder
            ->method('build')
            ->willReturn($mockTree);

        $result = $this->handler->handle($request);

        $this->assertEquals('completed', $result['status']);
        $this->assertArrayHasKey('request_id', $result);
        $this->assertStringStartsWith('a2a_', $result['request_id']);
    }

    public function testHandleMultipleChunksExtraction(): void
    {
        $request = [
            'intent' => 'extract_from_messages',
            'request_id' => 'test-multi-chunks',
            'payload' => [
                'messages' => [
                    ['id' => 'msg1', 'text' => 'Message 1'],
                    ['id' => 'msg2', 'text' => 'Message 2'],
                    ['id' => 'msg3', 'text' => 'Message 3'],
                ],
                'meta' => ['chat_id' => '789'],
            ],
        ];

        $mockChunks = [
            ['chunk_hash' => 'hash1', 'messages' => [['id' => 'msg1']]],
            ['chunk_hash' => 'hash2', 'messages' => [['id' => 'msg2']]],
            ['chunk_hash' => 'hash3', 'messages' => [['id' => 'msg3']]],
        ];

        $this->chunker
            ->method('chunk')
            ->willReturn($mockChunks);

        $this->publisher
            ->expects($this->exactly(3))
            ->method('publishChunk');

        $result = $this->handler->handle($request);

        $this->assertEquals([
            'status' => 'queued',
            'request_id' => 'test-multi-chunks',
            'result' => ['chunks_queued' => 3],
        ], $result);
    }

    public function testHandleVectorSearchMode(): void
    {
        $request = [
            'intent' => 'search_knowledge',
            'request_id' => 'test-vector-search',
            'payload' => [
                'query' => 'machine learning algorithms',
                'mode' => 'vector',
                'size' => 15,
            ],
        ];

        $mockEmbedding = array_fill(0, 1536, 0.1);
        $mockResults = [
            [
                'id' => 'ml-entry',
                'title' => 'Machine Learning Basics',
                'category' => 'AI',
            ],
        ];

        $this->embeddingService
            ->expects($this->once())
            ->method('embed')
            ->with('machine learning algorithms')
            ->willReturn($mockEmbedding);

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(
                'machine learning algorithms',
                'vector',
                ['size' => 15, 'embedding' => $mockEmbedding]
            )
            ->willReturn($mockResults);

        $result = $this->handler->handle($request);

        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('test-vector-search', $result['request_id']);
        $this->assertEquals(1, $result['result']['total']);
        $this->assertEquals($mockResults, $result['result']['entries']);
    }

    public function testHandleRequestWithDefaultValues(): void
    {
        $request = [
            'intent' => 'search_knowledge',
            'payload' => [
                'query' => 'test query',
                // No mode or size specified - should use defaults
            ],
        ];

        $this->embeddingService
            ->method('embed')
            ->willReturn([0.1, 0.2]);

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(
                'test query',
                'hybrid', // default mode
                ['size' => 10, 'embedding' => [0.1, 0.2]] // default size
            )
            ->willReturn([]);

        $result = $this->handler->handle($request);

        $this->assertEquals('completed', $result['status']);
        $this->assertStringStartsWith('a2a_', $result['request_id']); // auto-generated
        $this->assertEquals('hybrid', $result['result']['mode']);
    }
}