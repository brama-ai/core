<?php

declare(strict_types=1);

namespace App\A2AGateway;

interface SkillCatalogBuilderInterface
{
    /**
     * Build the skill catalog from all enabled agents.
     *
     * @return array<string, mixed>
     */
    public function build(): array;
}
