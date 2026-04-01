<?php

declare(strict_types=1);

namespace App\AgentRegistry;

/**
 * Value object representing a public endpoint declared in an agent manifest.
 */
final readonly class AgentPublicEndpoint
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        public int $id,
        public string $agentId,
        public string $agentName,
        public string $path,
        public array $methods,
        public ?string $description,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $methods = is_string($row['methods'])
            ? json_decode($row['methods'], true, 512, JSON_THROW_ON_ERROR)
            : (array) ($row['methods'] ?? []);

        return new self(
            id: (int) $row['id'],
            agentId: (string) $row['agent_id'],
            agentName: (string) ($row['agent_name'] ?? ''),
            path: (string) $row['path'],
            methods: array_values(array_map(strval(...), $methods)),
            description: isset($row['description']) ? (string) $row['description'] : null,
            createdAt: (string) $row['created_at'],
            updatedAt: (string) $row['updated_at'],
        );
    }
}
