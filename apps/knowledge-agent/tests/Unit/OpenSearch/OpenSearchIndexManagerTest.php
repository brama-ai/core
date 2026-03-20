<?php

declare(strict_types=1);

namespace App\Tests\Unit\OpenSearch;

use App\OpenSearch\OpenSearchIndexManager;
use Codeception\Test\Unit;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\MockObject\MockObject;

final class OpenSearchIndexManagerTest extends Unit
{
    private Client&MockObject $client;
    private IndicesNamespace&MockObject $indices;
    private OpenSearchIndexManager $indexManager;
    private string $indexName = 'test_knowledge_entries_v1';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(Client::class);
        $this->indices = $this->createMock(IndicesNamespace::class);
        
        $this->client
            ->method('indices')
            ->willReturn($this->indices);

        $this->indexManager = new OpenSearchIndexManager(
            $this->client,
            $this->indexName
        );
    }

    public function testIndexExistsReturnsTrueWhenIndexExists(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->with(['index' => $this->indexName])
            ->willReturn(true);

        $result = $this->indexManager->indexExists();

        $this->assertTrue($result);
    }

    public function testIndexExistsReturnsFalseWhenIndexDoesNotExist(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->with(['index' => $this->indexName])
            ->willReturn(false);

        $result = $this->indexManager->indexExists();

        $this->assertFalse($result);
    }

    public function testCreateIndexCreatesIndexWhenItDoesNotExist(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->with(['index' => $this->indexName])
            ->willReturn(false);

        $expectedCreateParams = [
            'index' => $this->indexName,
            'body' => [
                'settings' => [
                    'index' => [
                        'knn' => true,
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                    ],
                    'analysis' => [
                        'analyzer' => [
                            'ukrainian' => [
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'stop'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'ukrainian',
                        ],
                        'body' => [
                            'type' => 'text',
                            'analyzer' => 'ukrainian',
                        ],
                        'tags' => ['type' => 'keyword'],
                        'category' => ['type' => 'keyword'],
                        'tree_path' => ['type' => 'keyword'],
                        'embedding' => [
                            'type' => 'knn_vector',
                            'dimension' => 1536,
                            'method' => [
                                'name' => 'hnsw',
                                'space_type' => 'cosinesimil',
                                'engine' => 'nmslib',
                            ],
                        ],
                        'source_message_ids' => ['type' => 'keyword'],
                        'message_link' => ['type' => 'keyword'],
                        'created_by' => ['type' => 'keyword'],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ];

        $this->indices
            ->expects($this->once())
            ->method('create')
            ->with($expectedCreateParams);

        $this->indexManager->createIndex();
    }

    public function testCreateIndexDoesNothingWhenIndexAlreadyExists(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->with(['index' => $this->indexName])
            ->willReturn(true);

        $this->indices
            ->expects($this->never())
            ->method('create');

        $this->indexManager->createIndex();
    }

    public function testDeleteIndexDeletesIndexWhenItExists(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->with(['index' => $this->indexName])
            ->willReturn(true);

        $this->indices
            ->expects($this->once())
            ->method('delete')
            ->with(['index' => $this->indexName]);

        $this->indexManager->deleteIndex();
    }

    public function testDeleteIndexDoesNothingWhenIndexDoesNotExist(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->with(['index' => $this->indexName])
            ->willReturn(false);

        $this->indices
            ->expects($this->never())
            ->method('delete');

        $this->indexManager->deleteIndex();
    }

    public function testMappingValidationForKnnVector(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $this->indices
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $params): bool {
                $mapping = $params['body']['mappings']['properties'];
                
                // Validate knn_vector configuration
                $this->assertArrayHasKey('embedding', $mapping);
                $this->assertEquals('knn_vector', $mapping['embedding']['type']);
                $this->assertEquals(1536, $mapping['embedding']['dimension']);
                $this->assertEquals('hnsw', $mapping['embedding']['method']['name']);
                $this->assertEquals('cosinesimil', $mapping['embedding']['method']['space_type']);
                $this->assertEquals('nmslib', $mapping['embedding']['method']['engine']);
                
                return true;
            }));

        $this->indexManager->createIndex();
    }

    public function testMappingValidationForUkrainianAnalyzer(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $this->indices
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $params): bool {
                $settings = $params['body']['settings'];
                $mapping = $params['body']['mappings']['properties'];
                
                // Validate Ukrainian analyzer configuration
                $this->assertArrayHasKey('analysis', $settings);
                $this->assertArrayHasKey('analyzer', $settings['analysis']);
                $this->assertArrayHasKey('ukrainian', $settings['analysis']['analyzer']);
                $this->assertEquals('standard', $settings['analysis']['analyzer']['ukrainian']['tokenizer']);
                $this->assertEquals(['lowercase', 'stop'], $settings['analysis']['analyzer']['ukrainian']['filter']);
                
                // Validate text fields use Ukrainian analyzer
                $this->assertEquals('ukrainian', $mapping['title']['analyzer']);
                $this->assertEquals('ukrainian', $mapping['body']['analyzer']);
                
                return true;
            }));

        $this->indexManager->createIndex();
    }

    public function testMappingValidationForKeywordFields(): void
    {
        $this->indices
            ->expects($this->once())
            ->method('exists')
            ->willReturn(false);

        $this->indices
            ->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $params): bool {
                $mapping = $params['body']['mappings']['properties'];
                
                // Validate keyword fields
                $keywordFields = ['tags', 'category', 'tree_path', 'source_message_ids', 'message_link', 'created_by'];
                foreach ($keywordFields as $field) {
                    $this->assertArrayHasKey($field, $mapping);
                    $this->assertEquals('keyword', $mapping[$field]['type']);
                }
                
                // Validate date fields
                $dateFields = ['created_at', 'updated_at'];
                foreach ($dateFields as $field) {
                    $this->assertArrayHasKey($field, $mapping);
                    $this->assertEquals('date', $mapping[$field]['type']);
                }
                
                return true;
            }));

        $this->indexManager->createIndex();
    }
}