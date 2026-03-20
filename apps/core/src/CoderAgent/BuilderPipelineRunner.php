<?php

declare(strict_types=1);

namespace App\CoderAgent;

final class BuilderPipelineRunner implements PipelineRunnerInterface
{
    public function __construct(
        private readonly CoderPathLocator $paths,
        private readonly CoderCompatibilityBridge $bridge,
    ) {
    }

    /**
     * @param array<string, mixed> $task
     */
    public function run(array $task, string $workerId, callable $onLog, callable $onStageChanged): array
    {
        $this->paths->ensureDirectories();

        $builderTaskPath = is_string($task['builder_task_path'] ?? null) && '' !== (string) $task['builder_task_path']
            ? (string) $task['builder_task_path']
            : $this->bridge->renderTaskFile($task);

        $command = [
            'bash',
            $this->paths->pipelineScript,
            '--task-file',
            $builderTaskPath,
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes, $this->paths->repoRoot);
        if (!\is_resource($process)) {
            throw new \RuntimeException('Failed to start builder pipeline process.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stageProgress = $this->decodeStageProgress($task);
        $currentStage = null;
        $branchName = null;

        while (true) {
            foreach ([1 => 'info', 2 => 'error'] as $index => $level) {
                while (false !== ($line = fgets($pipes[$index]))) {
                    $line = rtrim($line, "\r\n");
                    if ('' === $line) {
                        continue;
                    }

                    $detectedStage = $this->detectStage($line);
                    if (null !== $detectedStage && $currentStage !== $detectedStage) {
                        $currentStage = $detectedStage;
                        $stageProgress[$detectedStage->value] = [
                            'status' => 'running',
                            'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                        ];
                        $onStageChanged($detectedStage);
                    }

                    if (str_contains($line, 'Branch:')) {
                        $branchName = trim((string) preg_replace('/^.*Branch:\s*/', '', $line));
                    }

                    $onLog($line, $currentStage, $level);
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }

            usleep(200000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (null !== $currentStage) {
            $stageProgress[$currentStage->value]['status'] = 0 === $exitCode ? 'done' : 'failed';
            $stageProgress[$currentStage->value]['updated_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        }

        $reconciled = $this->bridge->reconcileTask($task + ['builder_task_path' => $builderTaskPath]);
        $slug = $this->bridge->taskSlug($task);
        $worktreePath = is_dir($this->paths->worktreesDir.'/'.$slug) ? $this->paths->worktreesDir.'/'.$slug : null;

        return [
            'exit_code' => $exitCode,
            'status' => 0 === $exitCode ? TaskStatus::Done : TaskStatus::Failed,
            'current_stage' => $currentStage,
            'stage_progress' => $stageProgress,
            'branch_name' => $branchName,
            'worktree_path' => $worktreePath,
            'summary_path' => $reconciled['summary_path'],
            'artifacts_path' => $reconciled['artifacts_path'],
            'compat_state' => $reconciled['compat_state'],
            'builder_task_path' => $builderTaskPath,
        ];
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

    private function detectStage(string $line): ?TaskStage
    {
        $map = [
            'planner' => TaskStage::Planner,
            'architect' => TaskStage::Architect,
            'coder' => TaskStage::Coder,
            'auditor' => TaskStage::Auditor,
            'validator' => TaskStage::Validator,
            'tester' => TaskStage::Tester,
            'documenter' => TaskStage::Documenter,
            'summarizer' => TaskStage::Summarizer,
        ];

        $lower = strtolower($line);
        foreach ($map as $needle => $stage) {
            if (str_contains($lower, $needle)) {
                return $stage;
            }
        }

        return null;
    }
}
