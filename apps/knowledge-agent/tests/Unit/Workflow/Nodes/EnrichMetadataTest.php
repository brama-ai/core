<?php

declare(strict_types=1);

namespace App\Tests\Unit\Workflow\Nodes;

use App\Event\EnrichmentCompleteEvent;
use App\Event\ExtractionCompleteEvent;
use App\Workflow\Nodes\EnrichMetadata;
use NeuronAI\Workflow\Events\StopEvent;
use NeuronAI\Workflow\WorkflowState;
use PHPUnit\Framework\TestCase;

final class EnrichMetadataTest extends TestCase
{
    private EnrichMetadata $node;

    protected function setUp(): void
    {
        $this->node = new EnrichMetadata();
    }

    public function testReturnsStopEventWhenNoKnowledge(): void
    {
        $state = new WorkflowState(['knowledge' => null]);
        $event = new ExtractionCompleteEvent();

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(StopEvent::class, $result);
    }

    public function testEnrichesKnowledgeWithMetadata(): void
    {
        $knowledge = [
            'title' => 'Test Knowledge',
            'body' => 'Test body',
            'tags' => ['test'],
            'category' => 'Technology',
            'tree_path' => 'Technology/Test',
        ];

        $chunkMeta = [
            'message_ids' => ['123', '124', '125'],
            'chat_id' => '1001234567890',
            'created_by' => 'user123',
        ];

        $state = new WorkflowState([
            'knowledge' => $knowledge,
            'chunk_meta' => $chunkMeta,
        ]);
        $event = new ExtractionCompleteEvent();

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(EnrichmentCompleteEvent::class, $result);

        $enrichedKnowledge = $state->get('knowledge');
        $this->assertIsArray($enrichedKnowledge);
        
        // Original knowledge preserved
        $this->assertSame('Test Knowledge', $enrichedKnowledge['title']);
        $this->assertSame('Test body', $enrichedKnowledge['body']);
        $this->assertSame(['test'], $enrichedKnowledge['tags']);
        $this->assertSame('Technology', $enrichedKnowledge['category']);
        $this->assertSame('Technology/Test', $enrichedKnowledge['tree_path']);
        
        // Metadata added
        $this->assertSame(['123', '124', '125'], $enrichedKnowledge['source_message_ids']);
        $this->assertSame('https://t.me/c/1001234567890/123', $enrichedKnowledge['message_link']);
        $this->assertSame('user123', $enrichedKnowledge['created_by']);
        $this->assertSame('telegram', $enrichedKnowledge['source_type']);
    }

    public function testHandlesMissingChatIdOrMessageId(): void
    {
        $knowledge = [
            'title' => 'Test Knowledge',
            'body' => 'Test body',
        ];

        $chunkMeta = [
            'message_ids' => [],
            'chat_id' => null,
        ];

        $state = new WorkflowState([
            'knowledge' => $knowledge,
            'chunk_meta' => $chunkMeta,
        ]);
        $event = new ExtractionCompleteEvent();

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(EnrichmentCompleteEvent::class, $result);

        $enrichedKnowledge = $state->get('knowledge');
        $this->assertIsArray($enrichedKnowledge);
        
        $this->assertSame([], $enrichedKnowledge['source_message_ids']);
        $this->assertNull($enrichedKnowledge['message_link']);
        $this->assertSame('system', $enrichedKnowledge['created_by']);
        $this->assertSame('telegram', $enrichedKnowledge['source_type']);
    }

    public function testHandlesMissingChunkMeta(): void
    {
        $knowledge = [
            'title' => 'Test Knowledge',
            'body' => 'Test body',
        ];

        $state = new WorkflowState(['knowledge' => $knowledge]);
        $event = new ExtractionCompleteEvent();

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(EnrichmentCompleteEvent::class, $result);

        $enrichedKnowledge = $state->get('knowledge');
        $this->assertIsArray($enrichedKnowledge);
        
        $this->assertSame([], $enrichedKnowledge['source_message_ids']);
        $this->assertNull($enrichedKnowledge['message_link']);
        $this->assertSame('system', $enrichedKnowledge['created_by']);
        $this->assertSame('telegram', $enrichedKnowledge['source_type']);
    }

    public function testGeneratesCorrectTelegramLink(): void
    {
        $knowledge = ['title' => 'Test'];
        $chunkMeta = [
            'message_ids' => ['456', '457'],
            'chat_id' => '9876543210',
        ];

        $state = new WorkflowState([
            'knowledge' => $knowledge,
            'chunk_meta' => $chunkMeta,
        ]);
        $event = new ExtractionCompleteEvent();

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(EnrichmentCompleteEvent::class, $result);

        $enrichedKnowledge = $state->get('knowledge');
        $this->assertSame('https://t.me/c/9876543210/456', $enrichedKnowledge['message_link']);
    }

    public function testHandlesPartialChatIdOrMessageId(): void
    {
        $knowledge = ['title' => 'Test'];
        
        // Test with chat_id but no message_ids
        $chunkMeta1 = [
            'message_ids' => [],
            'chat_id' => '1234567890',
        ];

        $state1 = new WorkflowState([
            'knowledge' => $knowledge,
            'chunk_meta' => $chunkMeta1,
        ]);
        $event = new ExtractionCompleteEvent();

        $result1 = ($this->node)($event, $state1);
        $enrichedKnowledge1 = $state1->get('knowledge');
        $this->assertNull($enrichedKnowledge1['message_link']);

        // Test with message_ids but no chat_id
        $chunkMeta2 = [
            'message_ids' => ['123'],
            'chat_id' => null,
        ];

        $state2 = new WorkflowState([
            'knowledge' => $knowledge,
            'chunk_meta' => $chunkMeta2,
        ]);

        $result2 = ($this->node)($event, $state2);
        $enrichedKnowledge2 = $state2->get('knowledge');
        $this->assertNull($enrichedKnowledge2['message_link']);
    }
}