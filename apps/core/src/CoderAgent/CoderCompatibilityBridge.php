<?php

declare(strict_types=1);

namespace App\CoderAgent;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class CoderCompatibilityBridge implements CoderCompatibilityBridgeInterface
{
    private readonly AsciiSlugger $slugger;

    public function __construct(
        private readonly CoderPathLocator $paths,
        #[Autowire('%kernel.environment%')]
        private readonly string $appEnv,
    ) {
        $this->slugger = new AsciiSlugger();
    }

    public function renderTaskFile(array $task): string
    {
        $this->paths->ensureDirectories();

        $slug = $this->taskSlug($task);
        $path = $this->paths->todoDir.'/'.$slug.'.md';
        $lines = [
            sprintf('<!-- coder_task_id: %s -->', (string) $task['id']),
            sprintf('<!-- priority: %d -->', (int) ($task['priority'] ?? 1)),
            sprintf('<!-- template: %s -->', (string) ($task['template_type'] ?? TaskTemplateType::Custom->value)),
            sprintf('<!-- status_hint: %s -->', (string) ($task['status'] ?? TaskStatus::Draft->value)),
        ];

        if ('test' === $this->appEnv) {
            $lines[] = '<!-- source: autotest -->';
        }

        $lines[] = '# '.trim((string) $task['title']);
        $lines[] = '';
        $lines[] = rtrim((string) $task['description']);
        $lines[] = '';

        $content = implode("\n", $lines);

        file_put_contents($path, $content);

        return $path;
    }

    public function reconcileTask(array $task): array
    {
        $slug = $this->taskSlug($task);
        $builderPath = $this->findTaskFile((string) $task['id'], $slug);
        $status = null;

        if (null !== $builderPath) {
            if (str_starts_with($builderPath, $this->paths->inProgressDir.'/')) {
                $status = TaskStatus::InProgress;
            } elseif (str_starts_with($builderPath, $this->paths->doneDir.'/')) {
                $status = TaskStatus::Done;
            } elseif (str_starts_with($builderPath, $this->paths->failedDir.'/')) {
                $status = TaskStatus::Failed;
            } elseif (str_starts_with($builderPath, $this->paths->todoDir.'/')) {
                $status = \in_array((string) $task['status'], [TaskStatus::Queued->value, TaskStatus::Draft->value], true)
                    ? TaskStatus::from((string) $task['status'])
                    : TaskStatus::Queued;
            }
        }

        $summaryPath = $this->findSummaryPath($slug);
        $artifactsPath = is_dir($this->paths->artifactsDir.'/'.$slug) ? $this->paths->artifactsDir.'/'.$slug : null;

        return [
            'status' => $status,
            'builder_task_path' => $builderPath,
            'summary_path' => $summaryPath,
            'artifacts_path' => $artifactsPath,
            'compat_state' => [
                'slug' => $slug,
                'reconciled_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $task
     */
    public function taskSlug(array $task): string
    {
        $title = trim((string) ($task['title'] ?? 'task'));
        $slug = strtolower((string) $this->slugger->slug($title)->lower());
        $slug = trim(substr($slug, 0, 60), '-');

        return '' !== $slug ? $slug : 'task-'.substr((string) ($task['id'] ?? uniqid('', true)), 0, 8);
    }

    private function findTaskFile(string $taskId, string $slug): ?string
    {
        foreach ([$this->paths->todoDir, $this->paths->inProgressDir, $this->paths->doneDir, $this->paths->failedDir] as $dir) {
            $bySlug = $dir.'/'.$slug.'.md';
            if (is_file($bySlug)) {
                return $bySlug;
            }

            foreach (glob($dir.'/*.md') ?: [] as $file) {
                $first = fgets(\fopen($file, 'rb') ?: throw new \RuntimeException('Failed to open task file.'));
                if (false !== $first && str_contains($first, $taskId)) {
                    return $file;
                }
            }
        }

        return null;
    }

    private function findSummaryPath(string $slug): ?string
    {
        $matches = glob($this->paths->summaryDir.'/*-'.$slug.'.md') ?: [];
        if ([] === $matches) {
            return null;
        }

        rsort($matches);

        return $matches[0];
    }
}
