<?php

declare(strict_types=1);

namespace App\AgentDiscovery;

use App\AgentRegistry\AgentRegistryInterface;

final class DiscoveryBuilder
{
    public function __construct(
        private readonly AgentRegistryInterface $registry,
    ) {
    }

    /**
     * Build the OpenClaw-compatible tool catalog from all enabled agents.
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

            /** @var list<string> $capabilities */
            $capabilities = (array) ($manifest['capabilities'] ?? []);

            /** @var array<string, array<string, mixed>> $capabilitySchemas */
            $capabilitySchemas = (array) ($manifest['capability_schemas'] ?? []);

            foreach ($capabilities as $capability) {
                $capSchema = $capabilitySchemas[$capability] ?? [];
                $description = (string) ($capSchema['description'] ?? '');
                if ('' !== $configDescription) {
                    $description = $configDescription;
                } elseif ('' === $description) {
                    $description = $agentDescription;
                }
                $tools[] = [
                    'name' => $capability,
                    'agent' => $agentName,
                    'description' => $description,
                    'input_schema' => $capSchema['input_schema'] ?? ['type' => 'object'],
                ];
            }
        }

        return [
            'platform_version' => '0.1.0',
            'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
            'tools' => $tools,
        ];
    }
}
