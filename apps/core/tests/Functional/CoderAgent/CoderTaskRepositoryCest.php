<?php

declare(strict_types=1);

namespace App\Tests\Functional\CoderAgent;

use App\CoderAgent\CoderTaskRepository;
use App\CoderAgent\DTO\CreateCoderTaskRequest;
use App\CoderAgent\TaskStatus;
use App\CoderAgent\TaskTemplateType;

final class CoderTaskRepositoryCest
{
    public function createAndClaimTask(\FunctionalTester $I): void
    {
        /** @var CoderTaskRepository $repo */
        $repo = $I->grabService(CoderTaskRepository::class);

        $task = $repo->create(
            new CreateCoderTaskRequest(
                title: 'Test coder task '.bin2hex(random_bytes(4)),
                description: "## Goal\n\nCreate repo-backed task.",
                templateType: TaskTemplateType::Feature,
                priority: 7,
                pipelineConfig: ['skip_stages' => []],
                createdBy: 'functional-test',
                queueNow: true,
            ),
            '/tmp/test-coder-task.md',
        );

        $I->assertSame(TaskStatus::Queued->value, $task['status']);

        $claimed = $repo->claimNextQueuedTask('worker-test');
        $I->assertNotNull($claimed);
        $I->assertSame($task['id'], $claimed['id']);
        $I->assertSame(TaskStatus::InProgress->value, $claimed['status']);
        $I->assertSame('worker-test', $claimed['worker_id']);
    }
}
