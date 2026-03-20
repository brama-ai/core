<?php

declare(strict_types=1);

namespace App\Tests\Unit\CoderAgent;

use App\CoderAgent\TaskStage;
use App\CoderAgent\TaskStatus;
use App\CoderAgent\TaskTemplateType;
use App\CoderAgent\WorkerStatus;
use Codeception\Test\Unit;

final class EnumsTest extends Unit
{
    public function testTaskStatusCases(): void
    {
        $this->assertSame('draft', TaskStatus::Draft->value);
        $this->assertSame('queued', TaskStatus::Queued->value);
        $this->assertSame('in_progress', TaskStatus::InProgress->value);
        $this->assertSame('done', TaskStatus::Done->value);
        $this->assertSame('failed', TaskStatus::Failed->value);
        $this->assertSame('cancelled', TaskStatus::Cancelled->value);
    }

    public function testTaskStageCases(): void
    {
        $this->assertSame('planner', TaskStage::Planner->value);
        $this->assertSame('summarizer', TaskStage::Summarizer->value);
    }

    public function testWorkerStatusCases(): void
    {
        $this->assertSame('idle', WorkerStatus::Idle->value);
        $this->assertSame('busy', WorkerStatus::Busy->value);
        $this->assertSame('stopped', WorkerStatus::Stopped->value);
    }

    public function testTemplateCases(): void
    {
        $this->assertSame('feature', TaskTemplateType::Feature->value);
        $this->assertSame('bugfix', TaskTemplateType::Bugfix->value);
        $this->assertSame('refactor', TaskTemplateType::Refactor->value);
        $this->assertSame('custom', TaskTemplateType::Custom->value);
    }
}
