<?php

declare(strict_types=1);

namespace App\CoderAgent;

interface TaskTemplateCatalogInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array;

    /**
     * @return array<string, mixed>
     */
    public function get(TaskTemplateType $type): array;
}
