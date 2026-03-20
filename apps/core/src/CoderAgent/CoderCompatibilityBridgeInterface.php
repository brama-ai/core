<?php

declare(strict_types=1);

namespace App\CoderAgent;

interface CoderCompatibilityBridgeInterface
{
    /**
     * @param array<string, mixed> $task
     */
    public function renderTaskFile(array $task): string;

    /**
     * @param array<string, mixed> $task
     *
     * @return array{status: ?TaskStatus, builder_task_path: ?string, summary_path: ?string, artifacts_path: ?string, compat_state: array<string, mixed>}
     */
    public function reconcileTask(array $task): array;
}
