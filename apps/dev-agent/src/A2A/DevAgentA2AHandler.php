<?php

declare(strict_types=1);

namespace App\A2A;

use App\Logging\TraceEvent;
use App\Repository\DevTaskLogRepository;
use App\Repository\DevTaskRepository;
use Psr\Log\LoggerInterface;

final class DevAgentA2AHandler
{
    public function __construct(
        private readonly DevTaskRepository $taskRepo,
        private readonly DevTaskLogRepository $logRepo,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $intent = (string) ($data['intent'] ?? 'unknown');
        $payload = (array) ($data['payload'] ?? []);
        $requestId = (string) ($data['request_id'] ?? '');

        $logCtx = [
            'trace_id' => (string) ($data['trace_id'] ?? ''),
            'request_id' => $requestId,
        ];

        return match ($intent) {
            'dev.create_task' => $this->handleCreateTask($payload, $requestId, $logCtx),
            'dev.run_pipeline' => $this->handleRunPipeline($payload, $requestId, $logCtx),
            'dev.get_status' => $this->handleGetStatus($payload, $requestId, $logCtx),
            'dev.list_tasks' => $this->handleListTasks($payload, $requestId, $logCtx),
            default => $this->handleUnknown($intent, $requestId, $logCtx),
        };
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleCreateTask(array $payload, string $requestId, array $logCtx): array
    {
        $title = (string) ($payload['title'] ?? '');
        $description = (string) ($payload['description'] ?? '');

        if ('' === $title || '' === $description) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'title and description are required',
            ];
        }

        $options = json_encode($payload['pipeline_options'] ?? new \stdClass(), \JSON_THROW_ON_ERROR);
        $taskId = $this->taskRepo->insert($title, $description, $options);

        $this->logger->info(
            'Task created via A2A',
            TraceEvent::build('dev.task.created', 'a2a_handler', 'dev-agent', 'completed', array_merge($logCtx, [
                'task_id' => $taskId,
            ])),
        );

        return [
            'status' => 'completed',
            'request_id' => $requestId,
            'data' => ['task_id' => $taskId],
        ];
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleRunPipeline(array $payload, string $requestId, array $logCtx): array
    {
        $taskId = (int) ($payload['task_id'] ?? 0);
        if (0 === $taskId) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'task_id is required',
            ];
        }

        $task = $this->taskRepo->findById($taskId);
        if (null === $task) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'Task not found',
            ];
        }

        if (!\in_array((string) $task['status'], ['draft', 'failed'], true)) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'Task must be in draft or failed status to run',
            ];
        }

        $this->taskRepo->updateStatus($taskId, 'pending');

        $this->logger->info(
            'Pipeline queued via A2A',
            TraceEvent::build('dev.pipeline.queued', 'a2a_handler', 'dev-agent', 'completed', array_merge($logCtx, [
                'task_id' => $taskId,
            ])),
        );

        return [
            'status' => 'completed',
            'request_id' => $requestId,
            'data' => ['task_id' => $taskId, 'pipeline_status' => 'pending'],
        ];
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleGetStatus(array $payload, string $requestId, array $logCtx): array
    {
        $taskId = (int) ($payload['task_id'] ?? 0);
        if (0 === $taskId) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'task_id is required',
            ];
        }

        $task = $this->taskRepo->findById($taskId);
        if (null === $task) {
            return [
                'status' => 'failed',
                'request_id' => $requestId,
                'error' => 'Task not found',
            ];
        }

        $logCount = $this->logRepo->countByTaskId($taskId);

        return [
            'status' => 'completed',
            'request_id' => $requestId,
            'data' => [
                'task_id' => $taskId,
                'title' => $task['title'],
                'task_status' => $task['status'],
                'branch' => $task['branch'],
                'pr_url' => $task['pr_url'],
                'log_count' => $logCount,
                'created_at' => $task['created_at'],
                'started_at' => $task['started_at'],
                'finished_at' => $task['finished_at'],
            ],
        ];
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleListTasks(array $payload, string $requestId, array $logCtx): array
    {
        $statusFilter = isset($payload['status_filter']) ? (string) $payload['status_filter'] : null;
        $limit = (int) ($payload['limit'] ?? 20);
        $limit = min(max($limit, 1), 100);

        $tasks = $this->taskRepo->findRecent($limit, $statusFilter);

        $items = array_map(static fn (array $t): array => [
            'task_id' => $t['id'],
            'title' => $t['title'],
            'status' => $t['status'],
            'branch' => $t['branch'],
            'pr_url' => $t['pr_url'],
            'created_at' => $t['created_at'],
        ], $tasks);

        return [
            'status' => 'completed',
            'request_id' => $requestId,
            'data' => ['tasks' => $items, 'count' => \count($items)],
        ];
    }

    /**
     * @param array<string, string> $logCtx
     *
     * @return array<string, mixed>
     */
    private function handleUnknown(string $intent, string $requestId, array $logCtx): array
    {
        $this->logger->warning(
            "Unknown A2A intent: {$intent}",
            TraceEvent::build('dev.a2a.unknown_intent', 'a2a_handler', 'dev-agent', 'failed', array_merge($logCtx, [
                'intent' => $intent,
                'error_code' => 'unknown_intent',
            ])),
        );

        return [
            'status' => 'failed',
            'request_id' => $requestId,
            'error' => "Unknown intent: {$intent}",
        ];
    }
}
