<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2A\DTO;

use App\A2A\DTO\A2AResponse;
use App\A2A\DTO\A2AResponseStatus;
use Codeception\Test\Unit;

final class A2AResponseTest extends Unit
{
    public function testFromArrayCompletedStatus(): void
    {
        $response = A2AResponse::fromArray([
            'status' => 'completed',
            'request_id' => 'req-1',
            'result' => ['greeting' => 'Hello!'],
        ]);

        $this->assertSame(A2AResponseStatus::Completed, $response->status);
        $this->assertSame('req-1', $response->requestId);
        $this->assertSame(['greeting' => 'Hello!'], $response->result);
        $this->assertNull($response->error);
    }

    public function testFromArrayFailedStatus(): void
    {
        $response = A2AResponse::fromArray([
            'status' => 'failed',
            'request_id' => 'req-2',
            'error' => 'Something went wrong',
        ]);

        $this->assertSame(A2AResponseStatus::Failed, $response->status);
        $this->assertSame('Something went wrong', $response->error);
        $this->assertNull($response->result);
    }

    public function testFromArrayQueuedStatus(): void
    {
        $response = A2AResponse::fromArray([
            'status' => 'queued',
            'request_id' => 'req-3',
            'task_id' => 'task-abc',
        ]);

        $this->assertSame(A2AResponseStatus::Queued, $response->status);
        $this->assertSame('task-abc', $response->taskId);
    }

    public function testFromArrayUnknownStatusDefaultsToFailed(): void
    {
        $response = A2AResponse::fromArray(['status' => 'invalid_status']);

        $this->assertSame(A2AResponseStatus::Failed, $response->status);
    }

    public function testToArrayRoundtrip(): void
    {
        $response = A2AResponse::fromArray([
            'status' => 'completed',
            'request_id' => 'req-1',
            'result' => ['data' => 'value'],
            'task_id' => 'task-1',
        ]);

        $output = $response->toArray();

        $this->assertSame('completed', $output['status']);
        $this->assertSame('req-1', $output['request_id']);
        $this->assertSame(['data' => 'value'], $output['result']);
        $this->assertSame('task-1', $output['task_id']);
    }

    public function testToArrayOmitsNullFields(): void
    {
        $response = A2AResponse::fromArray([
            'status' => 'completed',
            'request_id' => 'req-1',
        ]);

        $output = $response->toArray();

        $this->assertArrayNotHasKey('result', $output);
        $this->assertArrayNotHasKey('error', $output);
        $this->assertArrayNotHasKey('task_id', $output);
    }
}
