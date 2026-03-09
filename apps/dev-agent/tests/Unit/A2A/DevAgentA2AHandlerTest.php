<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2A;

use App\A2A\DevAgentA2AHandler;
use App\Repository\DevTaskLogRepository;
use App\Repository\DevTaskRepository;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;

final class DevAgentA2AHandlerTest extends Unit
{
    private DevTaskRepository&MockObject $taskRepo;
    private DevTaskLogRepository&MockObject $logRepo;
    private DevAgentA2AHandler $handler;

    protected function setUp(): void
    {
        $this->taskRepo = $this->createMock(DevTaskRepository::class);
        $this->logRepo = $this->createMock(DevTaskLogRepository::class);

        $this->handler = new DevAgentA2AHandler(
            $this->taskRepo,
            $this->logRepo,
            new NullLogger(),
        );
    }

    public function testCreateTaskReturnsTaskId(): void
    {
        $this->taskRepo->method('insert')->willReturn(42);

        $result = $this->handler->handle([
            'intent' => 'dev.create_task',
            'payload' => ['title' => 'Test task', 'description' => 'Some desc'],
            'request_id' => 'req_1',
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(42, $result['data']['task_id']);
    }

    public function testCreateTaskFailsWithoutTitle(): void
    {
        $result = $this->handler->handle([
            'intent' => 'dev.create_task',
            'payload' => ['description' => 'No title'],
            'request_id' => 'req_2',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('title', $result['error']);
    }

    public function testRunPipelineSetsPending(): void
    {
        $this->taskRepo->method('findById')->willReturn([
            'id' => 1, 'status' => 'draft', 'title' => 'Test',
        ]);
        $this->taskRepo->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'pending');

        $result = $this->handler->handle([
            'intent' => 'dev.run_pipeline',
            'payload' => ['task_id' => 1],
            'request_id' => 'req_3',
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('pending', $result['data']['pipeline_status']);
    }

    public function testRunPipelineRejectsRunningTask(): void
    {
        $this->taskRepo->method('findById')->willReturn([
            'id' => 1, 'status' => 'running', 'title' => 'Test',
        ]);

        $result = $this->handler->handle([
            'intent' => 'dev.run_pipeline',
            'payload' => ['task_id' => 1],
            'request_id' => 'req_4',
        ]);

        $this->assertSame('failed', $result['status']);
    }

    public function testGetStatusReturnsTaskData(): void
    {
        $this->taskRepo->method('findById')->willReturn([
            'id' => 5, 'title' => 'Feature X', 'status' => 'success',
            'branch' => 'pipeline/feature-x', 'pr_url' => 'https://github.com/test/pull/1',
            'created_at' => '2026-03-09', 'started_at' => '2026-03-09', 'finished_at' => '2026-03-09',
        ]);
        $this->logRepo->method('countByTaskId')->willReturn(42);

        $result = $this->handler->handle([
            'intent' => 'dev.get_status',
            'payload' => ['task_id' => 5],
            'request_id' => 'req_5',
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('success', $result['data']['task_status']);
        $this->assertSame(42, $result['data']['log_count']);
    }

    public function testListTasksReturnsArray(): void
    {
        $this->taskRepo->method('findRecent')->willReturn([
            ['id' => 1, 'title' => 'A', 'status' => 'draft', 'branch' => null, 'pr_url' => null, 'created_at' => '2026-03-09'],
        ]);

        $result = $this->handler->handle([
            'intent' => 'dev.list_tasks',
            'payload' => [],
            'request_id' => 'req_6',
        ]);

        $this->assertSame('completed', $result['status']);
        $this->assertCount(1, $result['data']['tasks']);
    }

    public function testUnknownIntentFails(): void
    {
        $result = $this->handler->handle([
            'intent' => 'dev.unknown',
            'payload' => [],
            'request_id' => 'req_7',
        ]);

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('Unknown intent', $result['error']);
    }
}
