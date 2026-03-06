<?php

declare(strict_types=1);

namespace App\AgentRegistry;

final class ManifestValidator
{
    private const REQUIRED_FIELDS = [
        'name',
        'version',
    ];

    private const NAME_PATTERN = '/^[a-z][a-z0-9-]*$/';
    private const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';
    private const IDENTIFIER_PATTERN = '/^[a-z][a-z0-9_]*$/';
    private const COLLECTION_PATTERN = '/^[a-z][a-z0-9_-]*$/';

    /**
     * Normalize a manifest to the official A2A AgentCard structure.
     *
     * - Copies `a2a_endpoint` to `url` if `url` is absent
     * - Converts string skills to structured AgentSkill objects using `skill_schemas` data
     *
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>
     */
    public function normalize(array $manifest): array
    {
        // Normalize a2a_endpoint → url
        if (!isset($manifest['url']) && isset($manifest['a2a_endpoint'])) {
            $manifest['url'] = $manifest['a2a_endpoint'];
        }

        // Normalize string skills → AgentSkill objects
        if (isset($manifest['skills']) && is_array($manifest['skills'])) {
            /** @var array<string, array<string, mixed>> $skillSchemas */
            $skillSchemas = is_array($manifest['skill_schemas'] ?? null) ? $manifest['skill_schemas'] : [];

            $normalized = [];
            foreach ($manifest['skills'] as $skill) {
                if (is_string($skill)) {
                    $schema = $skillSchemas[$skill] ?? [];
                    $normalized[] = [
                        'id' => $skill,
                        'name' => $skill,
                        'description' => (string) ($schema['description'] ?? ''),
                        'tags' => [],
                    ];
                } elseif (is_array($skill)) {
                    $normalized[] = $skill;
                }
            }
            $manifest['skills'] = $normalized;
        }

        return $manifest;
    }

    /**
     * Extract skill IDs from a manifest's skills array (handles both string and structured formats).
     *
     * @param array<string, mixed> $manifest
     *
     * @return list<string>
     */
    public static function extractSkillIds(array $manifest): array
    {
        $ids = [];
        $skills = (array) ($manifest['skills'] ?? []);

        foreach ($skills as $skill) {
            if (is_string($skill)) {
                $ids[] = $skill;
            } elseif (is_array($skill) && isset($skill['id']) && is_string($skill['id'])) {
                $ids[] = $skill['id'];
            }
        }

        return $ids;
    }

    /**
     * Resolve the A2A endpoint URL from a manifest (prefers `url`, falls back to `a2a_endpoint`).
     *
     * @param array<string, mixed> $manifest
     */
    public static function resolveUrl(array $manifest): string
    {
        return (string) ($manifest['url'] ?? $manifest['a2a_endpoint'] ?? '');
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return list<string> list of validation error messages, empty if valid
     */
    public function validate(array $manifest): array
    {
        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $manifest)) {
                $errors[] = sprintf('Missing required field: %s', $field);
            }
        }

        if ([] !== $errors) {
            return $errors;
        }

        if (!is_string($manifest['name']) || !preg_match(self::NAME_PATTERN, $manifest['name'])) {
            $errors[] = 'Field "name" must be a non-empty kebab-case string (e.g. knowledge-base)';
        }

        if (!is_string($manifest['version']) || !preg_match(self::VERSION_PATTERN, $manifest['version'])) {
            $errors[] = 'Field "version" must be a semver string (e.g. 1.0.0)';
        }

        if (array_key_exists('description', $manifest)) {
            if (!is_string($manifest['description']) || '' === $manifest['description']) {
                $errors[] = 'Field "description" must be a non-empty string';
            }
        }

        foreach (['permissions', 'commands', 'events'] as $arrayField) {
            if (array_key_exists($arrayField, $manifest)) {
                if (!is_array($manifest[$arrayField])) {
                    $errors[] = sprintf('Field "%s" must be an array', $arrayField);
                } elseif (!$this->isStringArray($manifest[$arrayField])) {
                    $errors[] = sprintf('Field "%s" must be an array of strings', $arrayField);
                }
            }
        }

        // Validate url or a2a_endpoint
        $url = self::resolveUrl($manifest);
        if (array_key_exists('url', $manifest)) {
            if (!is_string($manifest['url']) || !filter_var($manifest['url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Field "url" must be a valid URL';
            }
        } elseif (array_key_exists('a2a_endpoint', $manifest)) {
            if (!is_string($manifest['a2a_endpoint']) || !filter_var($manifest['a2a_endpoint'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Field "a2a_endpoint" must be a valid URL';
            }
        }

        if (array_key_exists('config_schema', $manifest) && !is_array($manifest['config_schema'])) {
            $errors[] = 'Field "config_schema" must be an object';
        }

        // Validate skills (accepts string[] or AgentSkill[])
        if (array_key_exists('skills', $manifest)) {
            if (!is_array($manifest['skills'])) {
                $errors[] = 'Field "skills" must be an array';
            } else {
                $errors = array_merge($errors, $this->validateSkills(array_values($manifest['skills'])));
            }
        }

        if (array_key_exists('skill_schemas', $manifest) && !is_array($manifest['skill_schemas'])) {
            $errors[] = 'Field "skill_schemas" must be an object';
        }

        if (array_key_exists('health_url', $manifest)) {
            if (!is_string($manifest['health_url']) || !filter_var($manifest['health_url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Field "health_url" must be a valid URL';
            }
        }

        if (array_key_exists('documentationUrl', $manifest)) {
            if (!is_string($manifest['documentationUrl']) || !filter_var($manifest['documentationUrl'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Field "documentationUrl" must be a valid URL';
            }
        }

        // Validate provider
        if (array_key_exists('provider', $manifest)) {
            if (!is_array($manifest['provider'])) {
                $errors[] = 'Field "provider" must be an object';
            } else {
                $errors = array_merge($errors, $this->validateProvider($manifest['provider']));
            }
        }

        // Validate capabilities (A2A AgentCapabilities)
        if (array_key_exists('capabilities', $manifest)) {
            if (!is_array($manifest['capabilities'])) {
                $errors[] = 'Field "capabilities" must be an object';
            } else {
                $errors = array_merge($errors, $this->validateCapabilities($manifest['capabilities']));
            }
        }

        // Validate I/O modes
        foreach (['defaultInputModes', 'defaultOutputModes'] as $modesField) {
            if (array_key_exists($modesField, $manifest)) {
                if (!is_array($manifest[$modesField])) {
                    $errors[] = sprintf('Field "%s" must be an array', $modesField);
                } elseif (!$this->isStringArray($manifest[$modesField])) {
                    $errors[] = sprintf('Field "%s" must be an array of strings', $modesField);
                }
            }
        }

        if (array_key_exists('storage', $manifest)) {
            if (!is_array($manifest['storage'])) {
                $errors[] = 'Field "storage" must be an object';
            } else {
                $errors = array_merge($errors, $this->validateStorage($manifest['storage']));
            }
        }

        return $errors;
    }

    /**
     * @param list<mixed> $skills
     *
     * @return list<string>
     */
    private function validateSkills(array $skills): array
    {
        $errors = [];

        foreach ($skills as $i => $skill) {
            if (is_string($skill)) {
                continue; // Legacy string format — valid
            }

            if (!is_array($skill)) {
                $errors[] = sprintf('skills[%d] must be a string or AgentSkill object', $i);
                continue;
            }

            // Structured AgentSkill validation
            foreach (['id', 'name', 'description'] as $required) {
                if (!isset($skill[$required]) || !is_string($skill[$required])) {
                    $errors[] = sprintf('skills[%d].%s must be a non-empty string', $i, $required);
                }
            }

            if (isset($skill['tags']) && (!is_array($skill['tags']) || !$this->isStringArray($skill['tags']))) {
                $errors[] = sprintf('skills[%d].tags must be an array of strings', $i);
            }

            if (isset($skill['examples']) && (!is_array($skill['examples']) || !$this->isStringArray($skill['examples']))) {
                $errors[] = sprintf('skills[%d].examples must be an array of strings', $i);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $provider
     *
     * @return list<string>
     */
    private function validateProvider(array $provider): array
    {
        $errors = [];

        if (isset($provider['organization']) && (!is_string($provider['organization']) || '' === $provider['organization'])) {
            $errors[] = 'Field "provider.organization" must be a non-empty string';
        }

        if (isset($provider['url']) && (!is_string($provider['url']) || !filter_var($provider['url'], FILTER_VALIDATE_URL))) {
            $errors[] = 'Field "provider.url" must be a valid URL';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $capabilities
     *
     * @return list<string>
     */
    private function validateCapabilities(array $capabilities): array
    {
        $errors = [];

        foreach (['streaming', 'pushNotifications', 'stateTransitionHistory'] as $flag) {
            if (isset($capabilities[$flag]) && !is_bool($capabilities[$flag])) {
                $errors[] = sprintf('Field "capabilities.%s" must be a boolean', $flag);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $storage
     *
     * @return list<string>
     */
    private function validateStorage(array $storage): array
    {
        $errors = [];

        if (array_key_exists('postgres', $storage)) {
            if (!is_array($storage['postgres'])) {
                $errors[] = 'Field "storage.postgres" must be an object';
            } else {
                $errors = array_merge($errors, $this->validatePostgresStorage($storage['postgres']));
            }
        }

        if (array_key_exists('redis', $storage)) {
            if (!is_array($storage['redis'])) {
                $errors[] = 'Field "storage.redis" must be an object';
            } else {
                $errors = array_merge($errors, $this->validateRedisStorage($storage['redis']));
            }
        }

        if (array_key_exists('opensearch', $storage)) {
            if (!is_array($storage['opensearch'])) {
                $errors[] = 'Field "storage.opensearch" must be an object';
            } else {
                $errors = array_merge($errors, $this->validateOpenSearchStorage($storage['opensearch']));
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $postgres
     *
     * @return list<string>
     */
    private function validatePostgresStorage(array $postgres): array
    {
        $errors = [];

        foreach (['db_name', 'user', 'password'] as $field) {
            if (!array_key_exists($field, $postgres)) {
                $errors[] = sprintf('Missing required field: storage.postgres.%s', $field);
            } elseif (!is_string($postgres[$field]) || '' === $postgres[$field]) {
                $errors[] = sprintf('Field "storage.postgres.%s" must be a non-empty string', $field);
            }
        }

        if ([] !== $errors) {
            return $errors;
        }

        foreach (['db_name', 'user'] as $field) {
            if (!preg_match(self::IDENTIFIER_PATTERN, (string) $postgres[$field])) {
                $errors[] = sprintf('Field "storage.postgres.%s" must be a valid identifier (lowercase letters, digits, underscores)', $field);
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $redis
     *
     * @return list<string>
     */
    private function validateRedisStorage(array $redis): array
    {
        $errors = [];

        if (!array_key_exists('db_number', $redis)) {
            $errors[] = 'Missing required field: storage.redis.db_number';
        } elseif (!is_int($redis['db_number'])) {
            $errors[] = 'Field "storage.redis.db_number" must be an integer';
        } elseif ($redis['db_number'] < 0 || $redis['db_number'] > 15) {
            $errors[] = 'Field "storage.redis.db_number" must be between 0 and 15';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $opensearch
     *
     * @return list<string>
     */
    private function validateOpenSearchStorage(array $opensearch): array
    {
        $errors = [];

        if (!array_key_exists('collections', $opensearch)) {
            $errors[] = 'Missing required field: storage.opensearch.collections';

            return $errors;
        }

        if (!is_array($opensearch['collections'])) {
            $errors[] = 'Field "storage.opensearch.collections" must be an array';

            return $errors;
        }

        if ([] === $opensearch['collections']) {
            $errors[] = 'Field "storage.opensearch.collections" must not be empty';

            return $errors;
        }

        foreach ($opensearch['collections'] as $i => $collection) {
            if (!is_string($collection) || !preg_match(self::COLLECTION_PATTERN, $collection)) {
                $errors[] = sprintf('Field "storage.opensearch.collections[%d]" must be a valid identifier (lowercase letters, digits, underscores, hyphens)', $i);
            }
        }

        return $errors;
    }

    /**
     * @param mixed[] $array
     */
    private function isStringArray(array $array): bool
    {
        foreach ($array as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
