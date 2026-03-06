<?php

declare(strict_types=1);

namespace App\A2A\DTO;

/**
 * Full Agent Card (manifest) describing an A2A agent.
 *
 * This is the primary data structure agents expose at their manifest endpoint
 * (GET /api/v1/manifest). It contains agent metadata, skills, capabilities,
 * provider info, and configuration for the platform to discover and interact
 * with the agent.
 */
final readonly class AgentCard
{
    /**
     * @param list<AgentSkill>          $skills
     * @param array<string, mixed>      $skillSchemas
     * @param list<string>              $defaultInputModes
     * @param list<string>              $defaultOutputModes
     * @param list<string>              $permissions
     * @param list<string>              $commands
     * @param list<string>              $events
     * @param array<string, mixed>|null $storage
     * @param array<string, mixed>      $extra
     */
    public function __construct(
        /** @var string Agent name in kebab-case (e.g. "hello-agent") */
        public string $name,
        /** @var string Semantic version string (e.g. "1.0.0") */
        public string $version,
        /** @var string Human-readable description of the agent's purpose */
        public string $description = '',
        /** @var string A2A endpoint URL where the agent receives task requests */
        public string $url = '',
        /** @var AgentProvider|null Organization that provides this agent */
        public ?AgentProvider $provider = null,
        /** @var AgentCapabilities|null Protocol capabilities the agent supports */
        public ?AgentCapabilities $capabilities = null,
        /** @var list<AgentSkill> Skills (actions) this agent can perform */
        public array $skills = [],
        /** @var array<string, mixed> JSON Schema definitions for skill input payloads, keyed by skill ID */
        public array $skillSchemas = [],
        /** @var list<string> Default accepted input MIME types (e.g. ["text"]) */
        public array $defaultInputModes = [],
        /** @var list<string> Default output MIME types (e.g. ["text"]) */
        public array $defaultOutputModes = [],
        /** @var string|null URL to the agent's documentation */
        public ?string $documentationUrl = null,
        /** @var list<string> Required user permissions to invoke this agent */
        public array $permissions = [],
        /** @var list<string> Slash commands this agent handles (e.g. ["/hello"]) */
        public array $commands = [],
        /** @var list<string> Platform events this agent subscribes to (e.g. ["message.created"]) */
        public array $events = [],
        /** @var string|null Health check endpoint URL */
        public ?string $healthUrl = null,
        /** @var string|null Admin panel URL for this agent */
        public ?string $adminUrl = null,
        /** @var array<string, mixed>|null Storage requirements for provisioning (postgres, redis, opensearch) */
        public ?array $storage = null,
        /** @var array<string, mixed> Catch-all for unknown fields (forward compatibility) */
        public array $extra = [],
    ) {
    }

    /**
     * Hydrate an AgentCard from a raw manifest array.
     *
     * Handles backward compatibility:
     * - Normalizes deprecated `a2a_endpoint` to `url`
     * - Converts string skills to structured AgentSkill objects via `skill_schemas`
     * - Preserves unknown keys in `extra` for forward compatibility
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $url = (string) ($data['url'] ?? $data['a2a_endpoint'] ?? '');

        /** @var array<string, array<string, mixed>> $skillSchemas */
        $skillSchemas = \is_array($data['skill_schemas'] ?? null) ? $data['skill_schemas'] : [];

        $skills = [];
        foreach ((array) ($data['skills'] ?? []) as $skill) {
            if (\is_string($skill)) {
                /** @var array<string, mixed> $schema */
                $schema = $skillSchemas[$skill] ?? [];
                $skills[] = new AgentSkill(
                    id: $skill,
                    name: $skill,
                    description: (string) ($schema['description'] ?? ''),
                );
            } elseif (\is_array($skill)) {
                $skills[] = AgentSkill::fromArray($skill);
            }
        }

        $knownKeys = [
            'name', 'version', 'description', 'url', 'a2a_endpoint',
            'provider', 'capabilities', 'skills', 'skill_schemas',
            'defaultInputModes', 'defaultOutputModes', 'documentationUrl',
            'permissions', 'commands', 'events', 'health_url', 'admin_url',
            'storage', 'config_schema',
        ];
        $extra = array_diff_key($data, array_flip($knownKeys));

        if (isset($data['a2a_endpoint'])) {
            $extra['a2a_endpoint'] = (string) $data['a2a_endpoint'];
        }
        if (isset($data['config_schema']) && \is_array($data['config_schema'])) {
            $extra['config_schema'] = $data['config_schema'];
        }

        return new self(
            name: (string) ($data['name'] ?? ''),
            version: (string) ($data['version'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            url: $url,
            provider: isset($data['provider']) && \is_array($data['provider'])
                ? AgentProvider::fromArray($data['provider'])
                : null,
            capabilities: isset($data['capabilities']) && \is_array($data['capabilities'])
                ? AgentCapabilities::fromArray($data['capabilities'])
                : null,
            skills: $skills,
            skillSchemas: $skillSchemas,
            defaultInputModes: \is_array($data['defaultInputModes'] ?? null)
                ? array_values(array_map(strval(...), $data['defaultInputModes']))
                : [],
            defaultOutputModes: \is_array($data['defaultOutputModes'] ?? null)
                ? array_values(array_map(strval(...), $data['defaultOutputModes']))
                : [],
            documentationUrl: isset($data['documentationUrl']) ? (string) $data['documentationUrl'] : null,
            permissions: \is_array($data['permissions'] ?? null)
                ? array_values(array_map(strval(...), $data['permissions']))
                : [],
            commands: \is_array($data['commands'] ?? null)
                ? array_values(array_map(strval(...), $data['commands']))
                : [],
            events: \is_array($data['events'] ?? null)
                ? array_values(array_map(strval(...), $data['events']))
                : [],
            healthUrl: isset($data['health_url']) ? (string) $data['health_url'] : null,
            adminUrl: isset($data['admin_url']) ? (string) $data['admin_url'] : null,
            storage: isset($data['storage']) && \is_array($data['storage']) ? $data['storage'] : null,
            extra: $extra,
        );
    }

    /**
     * Serialize to a JSON-safe array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'version' => $this->version,
        ];

        if ('' !== $this->description) {
            $result['description'] = $this->description;
        }
        if ('' !== $this->url) {
            $result['url'] = $this->url;
        }
        if (null !== $this->provider) {
            $result['provider'] = $this->provider->toArray();
        }
        if (null !== $this->capabilities) {
            $result['capabilities'] = $this->capabilities->toArray();
        }
        if ([] !== $this->defaultInputModes) {
            $result['defaultInputModes'] = $this->defaultInputModes;
        }
        if ([] !== $this->defaultOutputModes) {
            $result['defaultOutputModes'] = $this->defaultOutputModes;
        }
        if ([] !== $this->skills) {
            $result['skills'] = array_map(
                static fn (AgentSkill $s): array => $s->toArray(),
                $this->skills,
            );
        }
        if ([] !== $this->skillSchemas) {
            $result['skill_schemas'] = $this->skillSchemas;
        }
        if (null !== $this->documentationUrl) {
            $result['documentationUrl'] = $this->documentationUrl;
        }
        if ([] !== $this->permissions) {
            $result['permissions'] = $this->permissions;
        }
        if ([] !== $this->commands) {
            $result['commands'] = $this->commands;
        }
        if ([] !== $this->events) {
            $result['events'] = $this->events;
        }
        if (null !== $this->healthUrl) {
            $result['health_url'] = $this->healthUrl;
        }
        if (null !== $this->adminUrl) {
            $result['admin_url'] = $this->adminUrl;
        }
        if (null !== $this->storage) {
            $result['storage'] = $this->storage;
        }

        return array_merge($result, $this->extra);
    }

    /**
     * Extract all skill IDs from the skills list.
     *
     * @return list<string>
     */
    public function skillIds(): array
    {
        return array_map(
            static fn (AgentSkill $s): string => $s->id,
            $this->skills,
        );
    }

    /**
     * Resolve the A2A endpoint URL, falling back to deprecated a2a_endpoint if stored in extra.
     */
    public function resolvedUrl(): string
    {
        if ('' !== $this->url) {
            return $this->url;
        }

        return (string) ($this->extra['a2a_endpoint'] ?? '');
    }
}
