<?php

declare(strict_types=1);

namespace App\CoderAgent\DTO;

final readonly class UpdateTaskPriorityRequest
{
    public function __construct(
        public string $taskId,
        public int $priority,
    ) {
    }
}
