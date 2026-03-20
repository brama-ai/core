<?php

declare(strict_types=1);

namespace App\CoderAgent;

use App\CoderAgent\DTO\CreateCoderTaskRequest;
use App\CoderAgent\DTO\UpdateTaskPriorityRequest;

final class CoderTaskService
{
    public function __construct(
        private readonly CoderTaskRepositoryInterface $tasks,
        private readonly CoderTaskLogRepositoryInterface $logs,
        private readonly CoderCompatibilityBridgeInterface $bridge,
        private readonly TaskEventPublisherInterface $events,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function create(CreateCoderTaskRequest $request): array
    {
        $this->assertValidCreateRequest($request);

        $draft = [
            'id' => 'pending',
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'template_type' => $request->templateType->value,
            'status' => $request->queueNow ? TaskStatus::Queued->value : TaskStatus::Draft->value,
        ];
        $builderTaskPath = $this->bridge->renderTaskFile($draft);
        $task = $this->tasks->create($request, $builderTaskPath);

        $realBuilderPath = $this->bridge->renderTaskFile($task);
        $this->tasks->updateRuntimeState(
            (string) $task['id'],
            TaskStatus::from((string) $task['status']),
            null,
            [],
            compatState: ['builder_task_path' => $realBuilderPath],
        );
        $task = $this->tasks->findById((string) $task['id']) ?? $task;

        $this->logs->append((string) $task['id'], null, 'info', 'system', 'Task created from admin UI.');
        $this->events->publish('task.status_changed', [
            'task_id' => $task['id'],
            'status' => $task['status'],
        ]);

        return $task;
    }

    public function queue(string $taskId): void
    {
        $task = $this->requireTask($taskId);
        $this->tasks->queue($taskId);
        $this->logs->append($taskId, null, 'info', 'system', 'Task queued.');
        $this->events->publish('task.status_changed', ['task_id' => $taskId, 'status' => TaskStatus::Queued->value]);
    }

    public function cancel(string $taskId): void
    {
        $this->requireTask($taskId);
        $this->tasks->cancel($taskId, 'Cancelled from admin.');
        $this->logs->append($taskId, null, 'warning', 'system', 'Task cancelled from admin.');
        $this->events->publish('task.status_changed', ['task_id' => $taskId, 'status' => TaskStatus::Cancelled->value]);
    }

    public function retry(string $taskId): void
    {
        $this->requireTask($taskId);
        $this->tasks->retry($taskId);
        $this->logs->append($taskId, null, 'info', 'system', 'Task retried and re-queued.');
        $this->events->publish('task.status_changed', ['task_id' => $taskId, 'status' => TaskStatus::Queued->value]);
    }

    public function delete(string $taskId): void
    {
        $task = $this->requireTask($taskId);
        $this->tasks->delete($taskId);
        $this->events->publish('task.status_changed', ['task_id' => $taskId, 'status' => 'deleted', 'title' => $task['title']]);
    }

    public function updatePriority(UpdateTaskPriorityRequest $request): void
    {
        $this->requireTask($request->taskId);
        $this->tasks->updatePriority($request->taskId, $request->priority);
        $this->logs->append($request->taskId, null, 'info', 'system', sprintf('Priority updated to %d.', $request->priority));
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcile(string $taskId): array
    {
        $task = $this->requireTask($taskId);
        $reconciled = $this->bridge->reconcileTask($task);
        $status = $reconciled['status'] ?? null;

        if ($status instanceof TaskStatus) {
            $this->tasks->updateRuntimeState(
                $taskId,
                $status,
                isset($task['current_stage']) && is_string($task['current_stage']) && '' !== $task['current_stage']
                    ? TaskStage::tryFrom((string) $task['current_stage'])
                    : null,
                $this->decodeStageProgress($task),
                summaryPath: $reconciled['summary_path'],
                artifactsPath: $reconciled['artifacts_path'],
                compatState: $reconciled['compat_state'],
            );

            if ($status->value !== (string) $task['status']) {
                $this->logs->append($taskId, null, 'warning', 'bridge', sprintf('Task reconciled from %s to %s.', (string) $task['status'], $status->value));
                $this->events->publish('task.reconciled_warning', [
                    'task_id' => $taskId,
                    'from_status' => $task['status'],
                    'to_status' => $status->value,
                ]);
            }
        }

        return $this->requireTask($taskId);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireTask(string $taskId): array
    {
        $task = $this->tasks->findById($taskId);
        if (null === $task) {
            throw new \InvalidArgumentException(sprintf('Task "%s" not found.', $taskId));
        }

        return $task;
    }

    private function assertValidCreateRequest(CreateCoderTaskRequest $request): void
    {
        if ('' === trim($request->title) || '' === trim($request->description)) {
            throw new \InvalidArgumentException('title and description are required');
        }
    }

    /**
     * @param array<string, mixed> $task
     *
     * @return array<string, mixed>
     */
    private function decodeStageProgress(array $task): array
    {
        $raw = $task['stage_progress'] ?? '[]';
        if (\is_array($raw)) {
            return $raw;
        }

        if (\is_string($raw)) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

                return \is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                return [];
            }
        }

        return [];
    }
}
