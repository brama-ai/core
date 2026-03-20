<?php

declare(strict_types=1);

namespace App\CoderAgent;

final class CoderWorkerService
{
    public function __construct(
        private readonly CoderTaskRepositoryInterface $tasks,
        private readonly CoderTaskLogRepositoryInterface $logs,
        private readonly CoderWorkerRepositoryInterface $workers,
        private readonly PipelineRunnerInterface $runner,
        private readonly CoderCompatibilityBridgeInterface $bridge,
        private readonly TaskEventPublisherInterface $events,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function runNextTask(string $workerId): ?array
    {
        $task = $this->tasks->claimNextQueuedTask($workerId);
        if (null === $task) {
            return null;
        }

        $reconciled = $this->bridge->reconcileTask($task);
        $status = $reconciled['status'];
        if ($status instanceof TaskStatus && TaskStatus::Done === $status) {
            return null;
        }

        $this->workers->heartbeat($workerId, WorkerStatus::Busy, (string) $task['id'], (string) ($task['worktree_path'] ?? null));
        $this->logs->append((string) $task['id'], null, 'info', 'system', sprintf('Worker %s claimed task.', $workerId));
        $this->events->publish('worker.status_changed', ['worker_id' => $workerId, 'status' => WorkerStatus::Busy->value, 'task_id' => $task['id']]);

        $startedAt = new \DateTimeImmutable();
        $result = $this->runner->run(
            $task,
            $workerId,
            function (string $line, ?TaskStage $stage, string $level) use ($task): void {
                $this->logs->append((string) $task['id'], $stage, $level, 'pipeline', $line);
                $this->events->publish('task.log', [
                    'task_id' => $task['id'],
                    'stage' => $stage?->value,
                    'level' => $level,
                    'message' => $line,
                ]);
            },
            function (TaskStage $stage) use ($task): void {
                $current = $this->tasks->findById((string) $task['id']) ?? $task;
                $progress = $this->decodeStageProgress($current);
                $progress[$stage->value] = [
                    'status' => 'running',
                    'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                ];
                $this->tasks->updateRuntimeState(
                    (string) $task['id'],
                    TaskStatus::InProgress,
                    $stage,
                    $progress,
                    workerId: (string) ($current['worker_id'] ?? null),
                    startedAt: $current['started_at'] instanceof \DateTimeImmutable ? $current['started_at'] : null,
                );
                $this->events->publish('task.stage_changed', ['task_id' => $task['id'], 'stage' => $stage->value]);
            },
        );

        $this->tasks->updateRuntimeState(
            (string) $task['id'],
            $result['status'],
            $result['current_stage'],
            $result['stage_progress'],
            0 === (int) $result['exit_code'] ? null : 'Builder pipeline failed.',
            branchName: $result['branch_name'],
            worktreePath: $result['worktree_path'],
            summaryPath: $result['summary_path'],
            artifactsPath: $result['artifacts_path'],
            workerId: 0 === (int) $result['exit_code'] ? null : $workerId,
            startedAt: $startedAt,
            finishedAt: new \DateTimeImmutable(),
            compatState: $result['compat_state'],
        );

        $this->workers->heartbeat($workerId, WorkerStatus::Idle);
        $this->events->publish('task.status_changed', ['task_id' => $task['id'], 'status' => $result['status']->value]);
        $this->events->publish('worker.status_changed', ['worker_id' => $workerId, 'status' => WorkerStatus::Idle->value]);

        return $this->tasks->findById((string) $task['id']);
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
