<?php

declare(strict_types=1);

namespace App\AgentDiscovery;

final class AgentConventionVerifier
{
    /**
     * @param array<string, mixed>|null $manifest
     */
    public function verify(?array $manifest): ConventionResult
    {
        if (null === $manifest) {
            return new ConventionResult('error', ['Manifest could not be parsed (invalid JSON or empty response)']);
        }

        $errors = [];
        $warnings = [];

        // --- Required fields (absence → error) ---
        if (empty($manifest['name']) || !is_string($manifest['name'])) {
            $errors[] = 'Required field missing or empty: name';
        } elseif (!preg_match('/^[a-z][a-z0-9-]*$/', (string) $manifest['name'])) {
            $warnings[] = 'Field "name" should be kebab-case (e.g. my-agent)';
        }

        if (empty($manifest['version']) || !is_string($manifest['version'])) {
            $errors[] = 'Required field missing or empty: version';
        } elseif (!preg_match('/^\d+\.\d+\.\d+$/', (string) $manifest['version'])) {
            $warnings[] = 'Field "version" should follow semver X.Y.Z (got: '.$manifest['version'].')';
        }

        if (!empty($errors)) {
            return new ConventionResult('error', $errors);
        }

        // --- Optional but recommended fields (absence → warning) ---
        if (!isset($manifest['capabilities'])) {
            $warnings[] = 'Field "capabilities" is missing (defaulting to empty array)';
        } elseif (!is_array($manifest['capabilities'])) {
            $warnings[] = 'Field "capabilities" must be an array';
        } elseif (!empty($manifest['capabilities']) && empty($manifest['a2a_endpoint'])) {
            $warnings[] = 'Field "a2a_endpoint" is required when "capabilities" is non-empty';
        }

        if (!empty($warnings)) {
            return new ConventionResult('degraded', $warnings);
        }

        return new ConventionResult('healthy', []);
    }
}
