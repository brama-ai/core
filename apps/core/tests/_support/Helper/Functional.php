<?php

declare(strict_types=1);

namespace Helper;

use App\Tenant\Tenant;
use App\Tenant\TenantContext;
use Codeception\Module;
use Codeception\TestInterface;

class Functional extends Module
{
    public function _before(TestInterface $test): void
    {
        $this->cleanupAutotestBuilderTasks();
        $this->setDefaultTenantContext();
    }

    private function setDefaultTenantContext(): void
    {
        try {
            $symfony = $this->getModule('Symfony');
            /** @var TenantContext $tenantContext */
            $tenantContext = $symfony->grabService(TenantContext::class);
            $defaultTenant = new Tenant(
                '00000000-0000-4000-a000-000000000001',
                'Default',
                'default',
                true,
                new \DateTimeImmutable(),
                new \DateTimeImmutable(),
            );
            $tenantContext->set($defaultTenant);
        } catch (\Exception) {
            // Silently skip if Symfony module is not available
        }
    }

    public function _after(TestInterface $test): void
    {
        $this->cleanupAutotestBuilderTasks();
    }

    private function cleanupAutotestBuilderTasks(): void
    {
        $repoRoot = dirname(__DIR__, 5);
        $tasksRoot = $repoRoot.'/builder/tasks';
        $dirs = [
            $tasksRoot.'/todo',
            $tasksRoot.'/in-progress',
            $tasksRoot.'/done',
            $tasksRoot.'/failed',
        ];

        foreach ($dirs as $dir) {
            foreach (glob($dir.'/*.md') ?: [] as $file) {
                $contents = @file_get_contents($file);
                if (false === $contents || !$this->isAutotestTask($contents)) {
                    continue;
                }

                @unlink($file);

                $basename = pathinfo($file, PATHINFO_FILENAME);
                foreach (glob($tasksRoot.'/summary/*-'.$basename.'.md') ?: [] as $summaryFile) {
                    @unlink($summaryFile);
                }

                $artifactDir = $tasksRoot.'/artifacts/'.$basename;
                if (is_dir($artifactDir)) {
                    $this->deleteDirectory($artifactDir);
                }
            }
        }
    }

    private function isAutotestTask(string $contents): bool
    {
        return str_contains($contents, '<!-- source: autotest -->')
            || str_contains($contents, '# Admin created task ');
    }

    private function deleteDirectory(string $path): void
    {
        foreach (glob($path.'/*') ?: [] as $item) {
            if (is_dir($item)) {
                $this->deleteDirectory($item);
            } else {
                @unlink($item);
            }
        }

        @rmdir($path);
    }
}
