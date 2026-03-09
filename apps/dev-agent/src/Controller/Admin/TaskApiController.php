<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\DevTaskLogRepository;
use App\Repository\DevTaskRepository;
use App\Service\LlmService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TaskApiController extends AbstractController
{
    public function __construct(
        private readonly DevTaskRepository $taskRepo,
        private readonly DevTaskLogRepository $logRepo,
        private readonly LlmService $llmService,
    ) {
    }

    #[Route('/admin/tasks/api/refine', name: 'admin_tasks_api_refine', methods: ['POST'])]
    public function refine(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        /** @var list<array{role: string, content: string}> $chatHistory */
        $chatHistory = (array) ($data['chat_history'] ?? []);
        $userMessage = (string) ($data['message'] ?? '');

        if ('' === $userMessage) {
            return $this->json(['error' => 'message is required'], Response::HTTP_BAD_REQUEST);
        }

        $chatHistory[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $reply = $this->llmService->chat($chatHistory);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'LLM request failed: '.$e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        $chatHistory[] = ['role' => 'assistant', 'content' => $reply];

        return $this->json([
            'reply' => $reply,
            'chat_history' => $chatHistory,
        ]);
    }

    #[Route('/admin/tasks/api/create', name: 'admin_tasks_api_create', methods: ['POST'])]
    public function createTask(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $title = (string) ($data['title'] ?? '');
        $description = (string) ($data['description'] ?? '');
        $refinedSpec = isset($data['refined_spec']) ? (string) $data['refined_spec'] : null;
        $autoStart = (bool) ($data['auto_start'] ?? false);
        $options = json_encode($data['pipeline_options'] ?? new \stdClass(), \JSON_THROW_ON_ERROR);

        if ('' === $title || '' === $description) {
            return $this->json(['error' => 'title and description are required'], Response::HTTP_BAD_REQUEST);
        }

        $taskId = $this->taskRepo->insert($title, $description, $options);

        if (null !== $refinedSpec) {
            /** @var list<array{role: string, content: string}> $chatHistory */
            $chatHistory = (array) ($data['chat_history'] ?? []);
            $this->taskRepo->updateRefinedSpec($taskId, $refinedSpec, $chatHistory);
        }

        if ($autoStart) {
            $this->taskRepo->updateStatus($taskId, 'pending');
        }

        return $this->json(['task_id' => $taskId]);
    }

    #[Route('/admin/tasks/api/{id}/start', name: 'admin_tasks_api_start', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function startPipeline(int $id): JsonResponse
    {
        $task = $this->taskRepo->findById($id);
        if (null === $task) {
            return $this->json(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        if (!\in_array((string) $task['status'], ['draft', 'failed'], true)) {
            return $this->json(['error' => 'Task must be in draft or failed status'], Response::HTTP_CONFLICT);
        }

        $this->taskRepo->updateStatus($id, 'pending');

        return $this->json(['status' => 'pending']);
    }

    #[Route('/admin/tasks/api/{id}/logs/stream', name: 'admin_tasks_api_logs_stream', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function streamLogs(int $id, Request $request): StreamedResponse
    {
        $lastId = (int) $request->query->get('last_id', '0');

        return new StreamedResponse(function () use ($id, $lastId): void {
            if (\function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
            ini_set('output_buffering', 'off');
            ini_set('zlib.output_compression', '0');

            $currentLastId = $lastId;
            $lastHeartbeat = time();

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                $entries = $this->logRepo->findByTaskId($id, $currentLastId);

                foreach ($entries as $entry) {
                    $data = json_encode([
                        'id' => (int) $entry['id'],
                        'agent_step' => $entry['agent_step'],
                        'level' => $entry['level'],
                        'message' => $entry['message'],
                        'created_at' => $entry['created_at'],
                    ], \JSON_THROW_ON_ERROR);

                    echo "id: {$entry['id']}\n";
                    echo "data: {$data}\n\n";
                    $currentLastId = (int) $entry['id'];
                }

                $task = $this->taskRepo->findById($id);
                if (null !== $task && \in_array((string) $task['status'], ['success', 'failed', 'cancelled'], true)) {
                    echo "event: complete\n";
                    echo 'data: '.json_encode(['status' => $task['status']])."\n\n";
                    flush();
                    break;
                }

                if (time() - $lastHeartbeat >= 15) {
                    echo ": heartbeat\n\n";
                    $lastHeartbeat = time();
                }

                flush();
                sleep(1);
            }
        }, Response::HTTP_OK, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}
