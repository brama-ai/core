<?php

declare(strict_types=1);

namespace App\Tenant;

final class Tenant
{
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $slug,
        private readonly bool $enabled,
        private readonly \DateTimeImmutable $createdAt,
        private readonly \DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
