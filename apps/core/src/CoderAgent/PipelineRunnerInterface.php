<?php

declare(strict_types=1);

namespace App\CoderAgent;

interface PipelineRunnerInterface
{
    /**
     * @param array<string, mixed>                           $task
     * @param callable(string, TaskStage|null, string): void $onLog
     * @param callable(TaskStage): void                      $onStageChanged
     *
     * @return array<string, mixed>
     */
    public function run(array $task, string $workerId, callable $onLog, callable $onStageChanged): array;
}
