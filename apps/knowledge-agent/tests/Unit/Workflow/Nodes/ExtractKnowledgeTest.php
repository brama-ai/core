<?php

declare(strict_types=1);

namespace App\Tests\Unit\Workflow\Nodes;

use App\Event\AnalysisCompleteEvent;
use App\Event\ExtractionCompleteEvent;
use App\Workflow\DTO\ExtractedKnowledge;
use App\Workflow\KnowledgeExtractionAgent;
use App\Workflow\Nodes\ExtractKnowledge;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\WorkflowState;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ExtractKnowledgeTest extends TestCase
{
    private MockObject&KnowledgeExtractionAgent $mockAgent;
    private ExtractKnowledge $node;

    protected function setUp(): void
    {
        $this->mockAgent = $this->createMock(KnowledgeExtractionAgent::class);
        $this->node = new ExtractKnowledge($this->mockAgent);
    }

    public function testExtractsKnowledgeFromMessages(): void
    {
        $messages = [
            ['from' => 'developer', 'text' => 'To fix CORS in Symfony, add this config'],
            ['from' => 'developer', 'text' => 'nelmio_cors: defaults: allow_origin: [\'*\']'],
        ];
        $state = new WorkflowState(['messages' => $messages]);
        $event = new AnalysisCompleteEvent();

        $extractedKnowledge = new ExtractedKnowledge();
        $extractedKnowledge->title = 'Налаштування CORS у Symfony';
        $extractedKnowledge->body = '## Налаштування CORS\n\nДля вирішення проблем з CORS у Symfony додайте конфігурацію:\n\n```yaml\nnelmio_cors:\n  defaults:\n    allow_origin: [\'*\']\n```';
        $extractedKnowledge->tags = ['symfony', 'cors', 'конфігурація'];
        $extractedKnowledge->category = 'Technology';
        $extractedKnowledge->treePath = 'Technology/PHP/Symfony';

        $this->mockAgent
            ->expects($this->once())
            ->method('structured')
            ->with(
                $this->callback(function (UserMessage $message): bool {
                    $content = $message->getContent();
                    return str_contains($content, 'Extract structured knowledge') 
                        && str_contains($content, '[developer]: To fix CORS in Symfony')
                        && str_contains($content, 'nelmio_cors: defaults');
                }),
                ExtractedKnowledge::class
            )
            ->willReturn($extractedKnowledge);

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(ExtractionCompleteEvent::class, $result);
        
        $knowledge = $state->get('knowledge');
        $this->assertIsArray($knowledge);
        $this->assertSame('Налаштування CORS у Symfony', $knowledge['title']);
        $this->assertStringContainsString('Налаштування CORS', $knowledge['body']);
        $this->assertSame(['symfony', 'cors', 'конфігурація'], $knowledge['tags']);
        $this->assertSame('Technology', $knowledge['category']);
        $this->assertSame('Technology/PHP/Symfony', $knowledge['tree_path']);
    }

    public function testHandlesEmptyMessages(): void
    {
        $state = new WorkflowState(['messages' => []]);
        $event = new AnalysisCompleteEvent();

        $extractedKnowledge = new ExtractedKnowledge();
        $extractedKnowledge->title = 'Порожнє повідомлення';
        $extractedKnowledge->body = 'Немає контенту для обробки';
        $extractedKnowledge->tags = [];
        $extractedKnowledge->category = 'Other';
        $extractedKnowledge->treePath = 'Other';

        $this->mockAgent
            ->expects($this->once())
            ->method('structured')
            ->with(
                $this->callback(function (UserMessage $message): bool {
                    $content = $message->getContent();
                    return str_contains($content, 'Extract structured knowledge') 
                        && str_contains($content, "\n\n"); // Empty message content
                }),
                ExtractedKnowledge::class
            )
            ->willReturn($extractedKnowledge);

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(ExtractionCompleteEvent::class, $result);
        
        $knowledge = $state->get('knowledge');
        $this->assertIsArray($knowledge);
        $this->assertSame('Порожнє повідомлення', $knowledge['title']);
    }

    public function testFormatsMessagesWithDifferentFieldNames(): void
    {
        $messages = [
            ['username' => 'user1', 'message' => 'First message'],
            ['from' => 'user2', 'text' => 'Second message'],
            ['text' => 'Third message without from'],
        ];
        $state = new WorkflowState(['messages' => $messages]);
        $event = new AnalysisCompleteEvent();

        $extractedKnowledge = new ExtractedKnowledge();
        $extractedKnowledge->title = 'Тестові повідомлення';
        $extractedKnowledge->body = 'Набір тестових повідомлень';
        $extractedKnowledge->tags = ['тест'];
        $extractedKnowledge->category = 'Other';
        $extractedKnowledge->treePath = 'Other/Test';

        $this->mockAgent
            ->expects($this->once())
            ->method('structured')
            ->with(
                $this->callback(function (UserMessage $message): bool {
                    $content = $message->getContent();
                    return str_contains($content, '[user1]: First message')
                        && str_contains($content, '[user2]: Second message')
                        && str_contains($content, '[Unknown]: Third message without from');
                }),
                ExtractedKnowledge::class
            )
            ->willReturn($extractedKnowledge);

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(ExtractionCompleteEvent::class, $result);
        
        $knowledge = $state->get('knowledge');
        $this->assertIsArray($knowledge);
        $this->assertSame('Тестові повідомлення', $knowledge['title']);
    }
}