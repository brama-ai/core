<?php

declare(strict_types=1);

namespace App\CoderAgent\DTO;

use App\CoderAgent\TaskTemplateType;

final readonly class CreateCoderTaskRequest
{
    /**
     * @param array<string, mixed> $pipelineConfig
     */
    public function __construct(
        public string $title,
        public string $description,
        public TaskTemplateType $templateType,
        public int $priority,
        public array $pipelineConfig,
        public string $createdBy,
        public bool $queueNow = false,
    ) {
    }
}
