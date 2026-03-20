<?php

declare(strict_types=1);

namespace App\A2AGateway;

use App\AgentRegistry\AgentRegistryInterface;
use App\AgentRegistry\ManifestValidator;

final class SkillCatalogBuilder implements SkillCatalogBuilderInterface
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
        private readonly ManifestValidator $manifestValidator,
    ) {
    }

    /**
     * Build the skill catalog from all enabled agents.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $tools = [];

        foreach ($this->registry->findEnabled() as $agent) {
            /** @var array<string, mixed> $manifest */
            $manifest = is_string($agent['manifest'])
                ? json_decode((string) $agent['manifest'], true, 512, JSON_THROW_ON_ERROR)
                : $agent['manifest'];

            $agentName = (string) $agent['name'];
            $agentDescription = (string) ($manifest['description'] ?? '');

            /** @var array<string, mixed> $config */
            $config = is_string($agent['config'] ?? null)
                ? (array) json_decode((string) $agent['config'], true)
                : (array) ($agent['config'] ?? []);
            $configDescription = (string) ($config['description'] ?? '');

            // Normalize manifest to get structured AgentSkill objects
            $normalized = $this->manifestValidator->normalize($manifest);

            /** @var list<array<string, mixed>> $skills */
            $skills = (array) ($normalized['skills'] ?? []);

            /** @var array<string, array<string, mixed>> $skillSchemas */
            $skillSchemas = (array) ($manifest['skill_schemas'] ?? []);

            foreach ($skills as $skill) {
                /** @var string $skillId */
                $skillId = (string) ($skill['id'] ?? '');
                $skillDescription = (string) ($skill['description'] ?? '');

                // Description priority: config > skill > agent
                $description = $skillDescription;
                if ('' !== $configDescription) {
                    $description = $configDescription;
                } elseif ('' === $description) {
                    $description = $agentDescription;
                }

                // Input schema from skill_schemas (legacy) or future structured skill extension
                $schema = $skillSchemas[$skillId] ?? [];
                $inputSchema = $schema['input_schema'] ?? ['type' => 'object'];

                $tool = [
                    'name' => $skillId,
                    'agent' => $agentName,
                    'description' => $description,
                    'input_schema' => $inputSchema,
                ];

                /** @var list<string> $tags */
                $tags = (array) ($skill['tags'] ?? []);
                if ([] !== $tags) {
                    $tool['tags'] = $tags;
                }

                $tools[] = $tool;
            }
        }

        return [
            'platform_version' => '0.1.0',
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
            'tools' => $tools,
        ];
    }
}
