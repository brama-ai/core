<?php

declare(strict_types=1);

namespace App\CoderAgent;

final class TaskTemplateCatalog implements TaskTemplateCatalogInterface
{
    public function all(): array
    {
        return [
            $this->get(TaskTemplateType::Feature),
            $this->get(TaskTemplateType::Bugfix),
            $this->get(TaskTemplateType::Refactor),
            $this->get(TaskTemplateType::Custom),
        ];
    }

    public function get(TaskTemplateType $type): array
    {
        return match ($type) {
            TaskTemplateType::Feature => [
                'id' => $type->value,
                'name' => 'Feature',
                'description' => "# Goal\n\nDescribe the feature to build.\n\n## Scope\n\n- In scope\n- Out of scope\n\n## Validation\n\n- Tests to update\n- Behavior to verify\n",
                'pipeline_config' => ['skip_stages' => [], 'worker_mode' => 'default'],
            ],
            TaskTemplateType::Bugfix => [
                'id' => $type->value,
                'name' => 'Bug Fix',
                'description' => "# Problem\n\nDescribe the current bug.\n\n## Expected Behavior\n\nDescribe the expected behavior.\n\n## Validation\n\n- Reproduction steps\n- Fix verification\n",
                'pipeline_config' => ['skip_stages' => ['architect', 'documenter'], 'worker_mode' => 'default'],
            ],
            TaskTemplateType::Refactor => [
                'id' => $type->value,
                'name' => 'Refactor',
                'description' => "# Goal\n\nDescribe the refactor objective.\n\n## Constraints\n\n- Preserve behavior\n- Keep scope bounded\n\n## Validation\n\n- Tests to keep green\n",
                'pipeline_config' => ['skip_stages' => ['architect'], 'worker_mode' => 'default'],
            ],
            TaskTemplateType::Custom => [
                'id' => $type->value,
                'name' => 'Custom',
                'description' => '',
                'pipeline_config' => ['skip_stages' => [], 'worker_mode' => 'default'],
            ],
        };
    }
}
