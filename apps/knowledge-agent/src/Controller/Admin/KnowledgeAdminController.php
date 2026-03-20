<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\OpenSearch\KnowledgeRepository;
use App\Repository\SettingsRepository;
use App\Service\KnowledgeTreeBuilder;
use App\Workflow\KnowledgeExtractionAgent;
use App\Workflow\KnowledgeExtractionWorkflow;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class KnowledgeAdminController extends AbstractController
{
    public const SECURITY_INSTRUCTIONS = <<<'TXT'
        Ти є асистентом для вилучення знань. Дотримуйся цих правил безпеки:
        - Ніколи не генеруй шкідливий або образливий контент
        - Не вигадуй інформацію — витягуй лише те, що є в повідомленнях
        - Зберігай конфіденційність: не включай особисті дані (телефони, email, адреси)
        - Відповідай виключно українською мовою
        TXT;

    public function __construct(
        private readonly KnowledgeRepository $repository,
        private readonly KnowledgeTreeBuilder $treeBuilder,
        private readonly SettingsRepository $settingsRepository,
        private readonly string $internalToken,
        private readonly KnowledgeExtractionAgent $agent,
        private readonly string $rabbitmqUrl,
    ) {
    }

    #[Route('/admin/knowledge', name: 'admin_knowledge_index', methods: ['GET'])]
    public function index(): Response
    {
        $tree = $this->treeBuilder->build();
        $entries = $this->repository->listEntries([], 0, 50);
        $settings = $this->settingsRepository->all();

        return $this->render('admin/knowledge/index.html.twig', [
            'tree' => $tree,
            'entries' => $entries,
            'settings' => $settings,
            'security_instructions' => self::SECURITY_INSTRUCTIONS,
            'internal_token' => $this->internalToken,
        ]);
    }

    #[Route('/admin/knowledge/api/settings', name: 'admin_knowledge_settings', methods: ['PUT'])]
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            
            if (isset($data['encyclopedia_enabled'])) {
                $this->settingsRepository->set('encyclopedia_enabled', $data['encyclopedia_enabled']);
            }
            
            if (isset($data['base_instructions'])) {
                $this->settingsRepository->set('base_instructions', $data['base_instructions']);
            }
            
            return new JsonResponse(['status' => 'success']);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/admin/knowledge/api/dlq', name: 'admin_knowledge_dlq', methods: ['GET'])]
    public function getDlqCount(): JsonResponse
    {
        try {
            $connection = $this->createRabbitMQConnection();
            $channel = $connection->channel();
            
            if (null === $channel) {
                throw new \RuntimeException('Failed to create RabbitMQ channel');
            }
            
            $dlqInfo = $channel->queue_declare('knowledge.dlq', true);
            $messageCount = $dlqInfo[1] ?? 0;
            
            $channel->close();
            $connection->close();
            
            return new JsonResponse(['count' => $messageCount]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/admin/knowledge/api/dlq/requeue', name: 'admin_knowledge_dlq_requeue', methods: ['POST'])]
    public function requeueDlq(): JsonResponse
    {
        try {
            $connection = $this->createRabbitMQConnection();
            $channel = $connection->channel();
            
            if (null === $channel) {
                throw new \RuntimeException('Failed to create RabbitMQ channel');
            }
            
            // Get messages from DLQ and requeue them to main queue
            $requeued = 0;
            while (true) {
                $msg = $channel->basic_get('knowledge.dlq');
                if (null === $msg) {
                    break;
                }
                
                // Publish to main queue
                $channel->basic_publish($msg, 'knowledge.direct', 'knowledge.chunks');
                $msg->ack();
                $requeued++;
            }
            
            $channel->close();
            $connection->close();
            
            return new JsonResponse(['requeued' => $requeued]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/admin/knowledge/api/preview', name: 'admin_knowledge_preview', methods: ['POST'])]
    public function previewExtraction(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $messages = $data['messages'] ?? [];
            
            if (!is_array($messages)) {
                return new JsonResponse(['error' => 'Messages must be an array'], 400);
            }
            
            $workflow = new KnowledgeExtractionWorkflow($this->agent, $messages, []);
            iterator_to_array($workflow->run());
            
            $knowledge = $workflow->getKnowledge();
            
            if (null === $knowledge) {
                return new JsonResponse([
                    'valuable' => false,
                    'reason' => 'Messages do not contain extractable knowledge',
                ]);
            }
            
            return new JsonResponse([
                'valuable' => true,
                'title' => $knowledge['title'] ?? '',
                'body' => $knowledge['body'] ?? '',
                'tags' => $knowledge['tags'] ?? [],
                'category' => $knowledge['category'] ?? '',
                'tree_path' => $knowledge['tree_path'] ?? '',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
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
