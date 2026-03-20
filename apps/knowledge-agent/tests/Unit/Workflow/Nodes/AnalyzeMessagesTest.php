<?php

declare(strict_types=1);

namespace App\Tests\Unit\Workflow\Nodes;

use App\Event\AnalysisCompleteEvent;
use App\Workflow\DTO\AnalysisResult;
use App\Workflow\KnowledgeExtractionAgent;
use App\Workflow\Nodes\AnalyzeMessages;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Workflow\Events\StartEvent;
use NeuronAI\Workflow\Events\StopEvent;
use NeuronAI\Workflow\WorkflowState;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AnalyzeMessagesTest extends TestCase
{
    private MockObject&KnowledgeExtractionAgent $mockAgent;
    private AnalyzeMessages $node;

    protected function setUp(): void
    {
        $this->mockAgent = $this->createMock(KnowledgeExtractionAgent::class);
        $this->node = new AnalyzeMessages($this->mockAgent);
    }

    public function testReturnsStopEventWhenNoMessages(): void
    {
        $state = new WorkflowState(['messages' => []]);
        $event = new StartEvent();

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(StopEvent::class, $result);
    }

    public function testReturnsStopEventWhenMessagesNotValuable(): void
    {
        $messages = [
            ['from' => 'user1', 'text' => 'Hello'],
            ['from' => 'user2', 'text' => 'Hi there'],
        ];
        $state = new WorkflowState(['messages' => $messages]);
        $event = new StartEvent();

        $analysisResult = new AnalysisResult();
        $analysisResult->isValuable = false;
        $analysisResult->reason = 'Just casual greetings, no extractable knowledge';

        $this->mockAgent
            ->expects($this->once())
            ->method('structured')
            ->with(
                $this->callback(function (UserMessage $message): bool {
                    $content = $message->getContent();
                    return str_contains($content, '[user1]: Hello') && str_contains($content, '[user2]: Hi there');
                }),
                AnalysisResult::class
            )
            ->willReturn($analysisResult);

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(StopEvent::class, $result);
        $this->assertFalse($state->get('is_valuable'));
        $this->assertSame('Just casual greetings, no extractable knowledge', $state->get('analysis_reason'));
    }

    public function testReturnsAnalysisCompleteEventWhenMessagesValuable(): void
    {
        $messages = [
            ['from' => 'developer', 'text' => 'To fix the CORS issue in Symfony, add this to your config/packages/nelmio_cors.yaml'],
            ['from' => 'developer', 'text' => 'nelmio_cors: defaults: origin_regex: true allow_origin: [\'%env(CORS_ALLOW_ORIGIN)%\']'],
        ];
        $state = new WorkflowState(['messages' => $messages]);
        $event = new StartEvent();

        $analysisResult = new AnalysisResult();
        $analysisResult->isValuable = true;
        $analysisResult->reason = 'Contains technical solution for CORS configuration in Symfony';

        $this->mockAgent
            ->expects($this->once())
            ->method('structured')
            ->with(
                $this->callback(function (UserMessage $message): bool {
                    $content = $message->getContent();
                    return str_contains($content, 'CORS issue in Symfony') && str_contains($content, 'nelmio_cors.yaml');
                }),
                AnalysisResult::class
            )
            ->willReturn($analysisResult);

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(AnalysisCompleteEvent::class, $result);
        $this->assertTrue($state->get('is_valuable'));
        $this->assertSame('Contains technical solution for CORS configuration in Symfony', $state->get('analysis_reason'));
    }

    public function testHandlesMessagesWithDifferentFieldNames(): void
    {
        $messages = [
            ['username' => 'user1', 'message' => 'Test message'],
            ['from' => 'user2', 'text' => 'Another message'],
            ['text' => 'Message without from field'],
        ];
        $state = new WorkflowState(['messages' => $messages]);
        $event = new StartEvent();

        $analysisResult = new AnalysisResult();
        $analysisResult->isValuable = false;
        $analysisResult->reason = 'Test messages';

        $this->mockAgent
            ->expects($this->once())
            ->method('structured')
            ->with(
                $this->callback(function (UserMessage $message): bool {
                    $content = $message->getContent();
                    return str_contains($content, '[user1]: Test message') 
                        && str_contains($content, '[user2]: Another message')
                        && str_contains($content, '[Unknown]: Message without from field');
                }),
                AnalysisResult::class
            )
            ->willReturn($analysisResult);

        $result = ($this->node)($event, $state);

        $this->assertInstanceOf(StopEvent::class, $result);
    }
}