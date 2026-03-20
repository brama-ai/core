<?php

declare(strict_types=1);

namespace App\CoderAgent;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CoderPathLocator
{
    public string $repoRoot;
    public string $builderTasksDir;
    public string $todoDir;
    public string $inProgressDir;
    public string $doneDir;
    public string $failedDir;
    public string $summaryDir;
    public string $artifactsDir;
    public string $pipelineLogsDir;
    public string $worktreesDir;
    public string $pipelineScript;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $coreProjectDir,
    ) {
        $this->repoRoot = dirname($coreProjectDir, 2);
        $this->builderTasksDir = $this->repoRoot.'/builder/tasks';
        $this->todoDir = $this->builderTasksDir.'/todo';
        $this->inProgressDir = $this->builderTasksDir.'/in-progress';
        $this->doneDir = $this->builderTasksDir.'/done';
        $this->failedDir = $this->builderTasksDir.'/failed';
        $this->summaryDir = $this->builderTasksDir.'/summary';
        $this->artifactsDir = $this->builderTasksDir.'/artifacts';
        $this->pipelineLogsDir = $this->repoRoot.'/.opencode/pipeline/logs';
        $this->worktreesDir = $this->repoRoot.'/.opencode/pipeline/worktrees';
        $this->pipelineScript = $this->repoRoot.'/builder/pipeline.sh';
    }

    public function ensureDirectories(): void
    {
        foreach ([
            $this->todoDir,
            $this->inProgressDir,
            $this->doneDir,
            $this->failedDir,
            $this->summaryDir,
            $this->artifactsDir,
            $this->pipelineLogsDir,
        ] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }
}
