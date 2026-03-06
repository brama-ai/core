<?php

declare(strict_types=1);

namespace App\AgentRegistry\DTO;

use App\A2A\DTO\AgentCard;

/**
 * A registered agent record from the agent_registry database table.
 *
 * Represents the full state of an agent in the platform, including its
 * manifest (AgentCard), configuration overrides, convention violations,
 * health status, and lifecycle timestamps.
 */
final readonly class RegisteredAgent
{
    /**
     * @param array<string, mixed> $config
     * @param list<string>         $violations
     */
    public function __construct(
        /** @var string UUID primary key */
        public string $id,
        /** @var string Agent name in kebab-case (e.g. "hello-agent") */
        public string $name,
        /** @var string Semantic version from the agent's manifest */
        public string $version,
        /** @var AgentCard Full agent manifest (Agent Card) */
        public AgentCard $manifest,
        /** @var array<string, mixed> Agent configuration overrides from admin settings */
        public array $config = [],
        /** @var list<string> Convention verification violation messages */
        public array $violations = [],
        /** @var bool Whether the agent is currently enabled for receiving requests */
        public bool $enabled = false,
        /** @var HealthStatus Current health check status */
        public HealthStatus $healthStatus = HealthStatus::Unknown,
        /** @var int Number of consecutive health check failures */
        public int $healthCheckFailures = 0,
        /** @var \DateTimeImmutable|null When the agent was first registered */
        public ?\DateTimeImmutable $registeredAt = null,
        /** @var \DateTimeImmutable|null Last time the agent's manifest was updated */
        public ?\DateTimeImmutable $updatedAt = null,
        /** @var \DateTimeImmutable|null When the agent was last enabled */
        public ?\DateTimeImmutable $enabledAt = null,
        /** @var \DateTimeImmutable|null When the agent was last disabled */
        public ?\DateTimeImmutable $disabledAt = null,
        /** @var string|null Username of the admin who enabled this agent */
        public ?string $enabledBy = null,
        /** @var \DateTimeImmutable|null When storage provisioning was completed */
        public ?\DateTimeImmutable $installedAt = null,
    ) {
    }

    /**
     * Hydrate from a database row.
     *
     * Handles JSON-encoded fields (manifest, config, violations) that may
     * arrive as strings from the database or as already-decoded arrays from cache.
     *
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        /** @var array<string, mixed> $manifestData */
        $manifestData = self::decodeJson($row['manifest'] ?? '{}');
        /** @var array<string, mixed> $configData */
        $configData = self::decodeJson($row['config'] ?? '{}');
        /** @var list<string> $violationsData */
        $violationsData = self::decodeJson($row['violations'] ?? '[]');

        return new self(
            id: (string) ($row['id'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            version: (string) ($row['version'] ?? ''),
            manifest: AgentCard::fromArray($manifestData),
            config: $configData,
            violations: array_map(strval(...), $violationsData),
            enabled: (bool) ($row['enabled'] ?? false),
            healthStatus: HealthStatus::tryFrom((string) ($row['health_status'] ?? '')) ?? HealthStatus::Unknown,
            healthCheckFailures: (int) ($row['health_check_failures'] ?? 0),
            registeredAt: self::parseDateTime($row['registered_at'] ?? null),
            updatedAt: self::parseDateTime($row['updated_at'] ?? null),
            enabledAt: self::parseDateTime($row['enabled_at'] ?? null),
            disabledAt: self::parseDateTime($row['disabled_at'] ?? null),
            enabledBy: isset($row['enabled_by']) ? (string) $row['enabled_by'] : null,
            installedAt: self::parseDateTime($row['installed_at'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'manifest' => $this->manifest->toArray(),
            'config' => $this->config,
            'violations' => $this->violations,
            'enabled' => $this->enabled,
            'health_status' => $this->healthStatus->value,
            'health_check_failures' => $this->healthCheckFailures,
            'registered_at' => $this->registeredAt?->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
            'enabled_at' => $this->enabledAt?->format(\DateTimeInterface::ATOM),
            'disabled_at' => $this->disabledAt?->format(\DateTimeInterface::ATOM),
            'enabled_by' => $this->enabledBy,
            'installed_at' => $this->installedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private static function decodeJson(mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (!\is_string($value) || '' === $value) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

            return \is_array($decoded) ? $decoded : [];
        } catch (\JsonException) {
            return [];
        }
    }

    private static function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if (!\is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
