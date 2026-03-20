<?php

declare(strict_types=1);

namespace App\Tests\Integration\Workflow;

use App\Workflow\DTO\AnalysisResult;
use App\Workflow\DTO\ExtractedKnowledge;
use App\Workflow\KnowledgeExtractionAgent;
use App\Workflow\KnowledgeExtractionWorkflow;
use NeuronAI\Chat\Messages\UserMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class KnowledgeExtractionWorkflowTest extends TestCase
{
    private MockObject&KnowledgeExtractionAgent $mockAgent;

    protected function setUp(): void
    {
        $this->mockAgent = $this->createMock(KnowledgeExtractionAgent::class);
    }

    public function testFullWorkflowWithValuableMessages(): void
    {
        $messages = [
            [
                'from' => 'developer',
                'text' => 'Щоб налаштувати CORS у Symfony, потрібно додати конфігурацію в nelmio_cors.yaml',
            ],
            [
                'from' => 'developer',
                'text' => 'nelmio_cors: defaults: allow_origin: [\'%env(CORS_ALLOW_ORIGIN)%\'] allow_methods: [GET, POST, PUT, DELETE]',
            ],
            [
                'from' => 'another_dev',
                'text' => 'Дякую! Це саме те, що мені потрібно було',
            ],
        ];

        $chunkMeta = [
            'message_ids' => ['msg_123', 'msg_124', 'msg_125'],
            'chat_id' => '1001234567890',
            'created_by' => 'telegram_bot',
        ];

        // Mock the analysis call
        $analysisResult = new AnalysisResult();
        $analysisResult->isValuable = true;
        $analysisResult->reason = 'Contains technical configuration instructions for Symfony CORS setup';

        // Mock the extraction call
        $extractedKnowledge = new ExtractedKnowledge();
        $extractedKnowledge->title = 'Налаштування CORS у Symfony';
        $extractedKnowledge->body = '## Налаштування CORS у Symfony\n\nДля налаштування CORS у Symfony додайте наступну конфігурацію в файл `config/packages/nelmio_cors.yaml`:\n\n```yaml\nnelmio_cors:\n  defaults:\n    allow_origin: [\'%env(CORS_ALLOW_ORIGIN)%\']\n    allow_methods: [GET, POST, PUT, DELETE]\n```\n\nЦе дозволить налаштувати дозволені домени через змінну оточення та методи HTTP запитів.';
        $extractedKnowledge->tags = ['symfony', 'cors', 'конфігурація', 'nelmio'];
        $extractedKnowledge->category = 'Technology';
        $extractedKnowledge->treePath = 'Technology/PHP/Symfony/Configuration';

        $this->mockAgent
            ->expects($this->exactly(2))
            ->method('structured')
            ->willReturnCallback(function (UserMessage $message, string $class) use ($analysisResult, $extractedKnowledge) {
                $content = $message->getContent();
                
                if ($class === AnalysisResult::class) {
                    $this->assertStringContainsString('Analyze this message batch', $content);
                    $this->assertStringContainsString('[developer]: Щоб налаштувати CORS', $content);
                    return $analysisResult;
                }
                
                if ($class === ExtractedKnowledge::class) {
                    $this->assertStringContainsString('Extract structured knowledge', $content);
                    $this->assertStringContainsString('nelmio_cors.yaml', $content);
                    return $extractedKnowledge;
                }
                
                throw new \InvalidArgumentException("Unexpected class: {$class}");
            });

        $workflow = new KnowledgeExtractionWorkflow($this->mockAgent, $messages, $chunkMeta);
        $workflow->run();

        $knowledge = $workflow->getKnowledge();
        $this->assertIsArray($knowledge);
        
        // Verify extracted knowledge
        $this->assertSame('Налаштування CORS у Symfony', $knowledge['title']);
        $this->assertStringContainsString('Налаштування CORS у Symfony', $knowledge['body']);
        $this->assertStringContainsString('nelmio_cors.yaml', $knowledge['body']);
        $this->assertSame(['symfony', 'cors', 'конфігурація', 'nelmio'], $knowledge['tags']);
        $this->assertSame('Technology', $knowledge['category']);
        $this->assertSame('Technology/PHP/Symfony/Configuration', $knowledge['tree_path']);
        
        // Verify enriched metadata
        $this->assertSame(['msg_123', 'msg_124', 'msg_125'], $knowledge['source_message_ids']);
        $this->assertSame('https://t.me/c/1001234567890/msg_123', $knowledge['message_link']);
        $this->assertSame('telegram_bot', $knowledge['created_by']);
        $this->assertSame('telegram', $knowledge['source_type']);
    }

    public function testWorkflowStopsWhenMessagesNotValuable(): void
    {
        $messages = [
            ['from' => 'user1', 'text' => 'Привіт!'],
            ['from' => 'user2', 'text' => 'Привіт! Як справи?'],
            ['from' => 'user1', 'text' => 'Все добре, дякую'],
        ];

        $chunkMeta = [
            'message_ids' => ['msg_001', 'msg_002', 'msg_003'],
            'chat_id' => '1001111111111',
        ];

        // Mock only the analysis call - extraction should not be called
        $analysisResult = new AnalysisResult();
        $analysisResult->isValuable = false;
        $analysisResult->reason = 'Just casual greetings and small talk, no extractable technical knowledge';

        $this->mockAgent
            ->expects($this->once())
            ->method('structured')
            ->with(
                $this->callback(function (UserMessage $message): bool {
                    $content = $message->getContent();
                    return str_contains($content, 'Analyze this message batch')
                        && str_contains($content, '[user1]: Привіт!')
                        && str_contains($content, '[user2]: Привіт! Як справи?');
                }),
                AnalysisResult::class
            )
            ->willReturn($analysisResult);

        $workflow = new KnowledgeExtractionWorkflow($this->mockAgent, $messages, $chunkMeta);
        $workflow->run();

        $knowledge = $workflow->getKnowledge();
        $this->assertNull($knowledge);
    }

    public function testWorkflowHandlesEmptyMessages(): void
    {
        $messages = [];
        $chunkMeta = [];

        // No LLM calls should be made for empty messages
        $this->mockAgent
            ->expects($this->never())
            ->method('structured');

        $workflow = new KnowledgeExtractionWorkflow($this->mockAgent, $messages, $chunkMeta);
        $workflow->run();

        $knowledge = $workflow->getKnowledge();
        $this->assertNull($knowledge);
    }

    public function testWorkflowWithMinimalChunkMeta(): void
    {
        $messages = [
            ['from' => 'expert', 'text' => 'Для оптимізації запитів у PostgreSQL використовуйте EXPLAIN ANALYZE'],
        ];

        $chunkMeta = []; // No metadata provided

        $analysisResult = new AnalysisResult();
        $analysisResult->isValuable = true;
        $analysisResult->reason = 'Contains database optimization advice';

        $extractedKnowledge = new ExtractedKnowledge();
        $extractedKnowledge->title = 'Оптимізація запитів PostgreSQL';
        $extractedKnowledge->body = 'Використовуйте `EXPLAIN ANALYZE` для аналізу продуктивності запитів у PostgreSQL.';
        $extractedKnowledge->tags = ['postgresql', 'оптимізація', 'explain'];
        $extractedKnowledge->category = 'Technology';
        $extractedKnowledge->treePath = 'Technology/Database/PostgreSQL';

        $this->mockAgent
            ->expects($this->exactly(2))
            ->method('structured')
            ->willReturnOnConsecutiveCalls($analysisResult, $extractedKnowledge);

        $workflow = new KnowledgeExtractionWorkflow($this->mockAgent, $messages, $chunkMeta);
        $workflow->run();

        $knowledge = $workflow->getKnowledge();
        $this->assertIsArray($knowledge);
        
        // Verify knowledge extraction worked
        $this->assertSame('Оптимізація запитів PostgreSQL', $knowledge['title']);
        
        // Verify metadata defaults are applied
        $this->assertSame([], $knowledge['source_message_ids']);
        $this->assertNull($knowledge['message_link']);
        $this->assertSame('system', $knowledge['created_by']);
        $this->assertSame('telegram', $knowledge['source_type']);
    }

    public function testWorkflowWithComplexTechnicalDiscussion(): void
    {
        $messages = [
            [
                'from' => 'senior_dev',
                'text' => 'У нас проблема з N+1 запитами в Doctrine ORM. Як це вирішити?',
            ],
            [
                'from' => 'architect',
                'text' => 'Використовуйте eager loading з join або fetch. Наприклад: $query->leftJoin(\'entity.relation\')->addSelect(\'relation\')',
            ],
            [
                'from' => 'senior_dev',
                'text' => 'А що з batch loading?',
            ],
            [
                'from' => 'architect',
                'text' => 'Так, можна також використовувати ->fetch(\'EXTRA_LAZY\') для lazy collections або DataLoader pattern',
            ],
        ];

        $chunkMeta = [
            'message_ids' => ['tech_001', 'tech_002', 'tech_003', 'tech_004'],
            'chat_id' => '1002345678901',
            'created_by' => 'tech_discussion_bot',
        ];

        $analysisResult = new AnalysisResult();
        $analysisResult->isValuable = true;
        $analysisResult->reason = 'Technical discussion about solving N+1 query problem in Doctrine ORM with specific solutions';

        $extractedKnowledge = new ExtractedKnowledge();
        $extractedKnowledge->title = 'Вирішення проблеми N+1 запитів у Doctrine ORM';
        $extractedKnowledge->body = '## Вирішення проблеми N+1 запитів у Doctrine ORM\n\n### Eager Loading з JOIN\n```php\n$query->leftJoin(\'entity.relation\')\n      ->addSelect(\'relation\')\n```\n\n### Lazy Collections\n```php\n->fetch(\'EXTRA_LAZY\')\n```\n\n### DataLoader Pattern\nВикористовуйте DataLoader pattern для batch loading операцій.';
        $extractedKnowledge->tags = ['doctrine', 'orm', 'n+1', 'оптимізація', 'php'];
        $extractedKnowledge->category = 'Technology';
        $extractedKnowledge->treePath = 'Technology/PHP/Doctrine/Performance';

        $this->mockAgent
            ->expects($this->exactly(2))
            ->method('structured')
            ->willReturnOnConsecutiveCalls($analysisResult, $extractedKnowledge);

        $workflow = new KnowledgeExtractionWorkflow($this->mockAgent, $messages, $chunkMeta);
        $workflow->run();

        $knowledge = $workflow->getKnowledge();
        $this->assertIsArray($knowledge);
        
        $this->assertSame('Вирішення проблеми N+1 запитів у Doctrine ORM', $knowledge['title']);
        $this->assertStringContainsString('leftJoin', $knowledge['body']);
        $this->assertStringContainsString('EXTRA_LAZY', $knowledge['body']);
        $this->assertContains('doctrine', $knowledge['tags']);
        $this->assertContains('n+1', $knowledge['tags']);
        $this->assertSame('Technology/PHP/Doctrine/Performance', $knowledge['tree_path']);
        
        $this->assertSame(['tech_001', 'tech_002', 'tech_003', 'tech_004'], $knowledge['source_message_ids']);
        $this->assertSame('https://t.me/c/1002345678901/tech_001', $knowledge['message_link']);
        $this->assertSame('tech_discussion_bot', $knowledge['created_by']);
    }
}