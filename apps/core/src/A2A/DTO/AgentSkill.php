<?php

declare(strict_types=1);

namespace App\A2A\DTO;

/**
 * An individual skill (capability) that an A2A agent exposes.
 *
 * Each skill represents a distinct action the agent can perform, identified
 * by a unique ID and described with metadata for discovery and invocation.
 */
final readonly class AgentSkill
{
    /**
     * @param list<string> $tags
     * @param list<string> $examples
     * @param list<string> $inputModes
     * @param list<string> $outputModes
     */
    public function __construct(
        /** @var string Unique skill identifier (e.g. "hello.greet", "knowledge.search") */
        public string $id,
        /** @var string Human-readable skill name (e.g. "Hello Greet") */
        public string $name,
        /** @var string Description of what this skill does */
        public string $description = '',
        /** @var list<string> Categorization tags for discovery (e.g. ["greeting", "search"]) */
        public array $tags = [],
        /** @var list<string> Example natural language prompts that trigger this skill */
        public array $examples = [],
        /** @var list<string> Supported input MIME types (e.g. ["text", "image"]) */
        public array $inputModes = [],
        /** @var list<string> Supported output MIME types */
        public array $outputModes = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? $data['id'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            tags: self::toStringList($data['tags'] ?? []),
            examples: self::toStringList($data['examples'] ?? []),
            inputModes: self::toStringList($data['inputModes'] ?? []),
            outputModes: self::toStringList($data['outputModes'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];

        if ([] !== $this->tags) {
            $result['tags'] = $this->tags;
        }
        if ([] !== $this->examples) {
            $result['examples'] = $this->examples;
        }
        if ([] !== $this->inputModes) {
            $result['inputModes'] = $this->inputModes;
        }
        if ([] !== $this->outputModes) {
            $result['outputModes'] = $this->outputModes;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function toStringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map(strval(...), $value));
    }
}
