<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\DevTaskLogRepository;
use App\Repository\DevTaskRepository;
use Psr\Log\LoggerInterface;

final class PipelineRunner
{
    public function __construct(
        private readonly DevTaskRepository $taskRepo,
        private readonly DevTaskLogRepository $logRepo,
        private readonly GitHubService $githubService,
        private readonly LoggerInterface $logger,
        private readonly string $repoRoot,
    ) {
    }

    public function run(int $taskId): void
    {
        $task = $this->taskRepo->findById($taskId);
        if (null === $task) {
            throw new \RuntimeException("Task {$taskId} not found");
        }

        $branch = 'pipeline/'.$this->slugify((string) $task['title']);
        $spec = (string) ($task['refined_spec'] ?? $task['description']);

        $taskFile = tempnam(sys_get_temp_dir(), 'dev_task_');
        if (false === $taskFile) {
            throw new \RuntimeException('Failed to create temp file');
        }
        file_put_contents($taskFile, "# {$task['title']}\n\n{$spec}");

        $options = json_decode((string) ($task['pipeline_options'] ?? '{}'), true) ?: [];
        $cmd = \sprintf(
            '%s/scripts/pipeline.sh --branch %s --task-file %s',
            $this->repoRoot,
            escapeshellarg($branch),
            escapeshellarg($taskFile),
        );

        if ($options['skip_architect'] ?? false) {
            $cmd .= ' --skip-architect';
        }
        if ($options['audit'] ?? false) {
            $cmd .= ' --audit';
        }

        $pipelineId = date('Ymd_His');
        $this->taskRepo->updateStatus($taskId, 'running', [
            'branch' => $branch,
            'pipeline_id' => $pipelineId,
            'started_at' => 'now()',
        ]);

        $this->logRepo->append($taskId, null, 'info', "Pipeline started: {$pipelineId}");
        $this->logRepo->append($taskId, null, 'info', "Branch: {$branch}");

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $this->repoRoot);

        if (!\is_resource($process)) {
            @unlink($taskFile);
            $this->taskRepo->updateStatus($taskId, 'failed', [
                'error_message' => 'Failed to start pipeline process',
                'finished_at' => 'now()',
            ]);

            return;
        }

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $currentAgent = null;
        $startTime = time();

        while (true) {
            $stdout = fgets($pipes[1]);
            $stderr = fgets($pipes[2]);

            if (false !== $stdout && '' !== trim($stdout)) {
                if (preg_match('/(?:Running|Agent):\s*(\w[\w-]*)/', $stdout, $m)) {
                    $currentAgent = strtolower($m[1]);
                }
                $this->logRepo->append($taskId, $currentAgent, 'info', rtrim($stdout));
            }

            if (false !== $stderr && '' !== trim($stderr)) {
                $this->logRepo->append($taskId, $currentAgent, 'error', rtrim($stderr));
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                while ($line = fgets($pipes[1])) {
                    $this->logRepo->append($taskId, $currentAgent, 'info', rtrim($line));
                }
                while ($line = fgets($pipes[2])) {
                    $this->logRepo->append($taskId, $currentAgent, 'error', rtrim($line));
                }
                break;
            }

            usleep(50_000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        @unlink($taskFile);

        $duration = time() - $startTime;
        $finalStatus = 0 === $exitCode ? 'success' : 'failed';

        $this->taskRepo->updateStatus($taskId, $finalStatus, [
            'finished_at' => 'now()',
            'duration_seconds' => $duration,
        ]);

        $this->logRepo->append($taskId, null, 'info', "Pipeline finished: {$finalStatus} ({$duration}s)");

        if ('success' === $finalStatus) {
            try {
                $prUrl = $this->githubService->createPr($taskId, $branch, (string) $task['title']);
                if (null !== $prUrl) {
                    $this->taskRepo->updatePr($taskId, $prUrl);
                    $this->logRepo->append($taskId, null, 'info', "PR created: {$prUrl}");
                }
            } catch (\Throwable $e) {
                $this->logRepo->append($taskId, null, 'warn', "PR creation failed: {$e->getMessage()}");
                $this->logger->warning('PR creation failed', ['task_id' => $taskId, 'error' => $e->getMessage()]);
            }
        }
    }

    private function slugify(string $text): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($text)));

        return substr(trim((string) $slug, '-'), 0, 60);
    }
}
