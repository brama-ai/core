<?php

declare(strict_types=1);

namespace App\A2AGateway;

use App\AgentRegistry\ManifestValidator;

final class AgentConventionVerifier
{
    /**
     * @param array<string, mixed>|null $agentCard
     */
    public function verify(?array $agentCard): ConventionResult
    {
        if (null === $agentCard) {
            return new ConventionResult('error', ['Agent Card could not be parsed (invalid JSON or empty response)']);
        }

        $errors = [];
        $warnings = [];

        // --- Required fields (absence → error) ---
        if (empty($agentCard['name']) || !is_string($agentCard['name'])) {
            $errors[] = 'Required field missing or empty: name';
        } elseif (!preg_match('/^[a-z][a-z0-9-]*$/', (string) $agentCard['name'])) {
            $warnings[] = 'Field "name" should be kebab-case (e.g. my-agent)';
        }

        if (empty($agentCard['version']) || !is_string($agentCard['version'])) {
            $errors[] = 'Required field missing or empty: version';
        } elseif (!preg_match('/^\d+\.\d+\.\d+$/', (string) $agentCard['version'])) {
            $warnings[] = 'Field "version" should follow semver X.Y.Z (got: '.$agentCard['version'].')';
        }

        if (!empty($errors)) {
            return new ConventionResult('error', $errors);
        }

        // --- URL field (prefer "url", accept "a2a_endpoint" with deprecation warning) ---
        $url = ManifestValidator::resolveUrl($agentCard);
        if (isset($agentCard['a2a_endpoint']) && !isset($agentCard['url'])) {
            $warnings[] = 'Field "a2a_endpoint" is deprecated — use "url" per official A2A spec';
        }

        // --- Skills ---
        $skillIds = ManifestValidator::extractSkillIds($agentCard);

        if (!isset($agentCard['skills'])) {
            $warnings[] = 'Field "skills" is missing (defaulting to empty array)';
        } elseif (!is_array($agentCard['skills'])) {
            $warnings[] = 'Field "skills" must be an array';
        } else {
            if (!empty($skillIds) && '' === $url) {
                $warnings[] = 'Field "url" (or "a2a_endpoint") is required when "skills" is non-empty';
            }

            // Check if skills are still using legacy string format
            $hasLegacyStrings = false;
            foreach ($agentCard['skills'] as $skill) {
                if (is_string($skill)) {
                    $hasLegacyStrings = true;
                    break;
                }
            }
            if ($hasLegacyStrings && !empty($agentCard['skills'])) {
                $warnings[] = 'Skills use legacy string format — consider structured AgentSkill objects per A2A spec';
            }
        }

        // --- A2A capabilities ---
        if (!isset($agentCard['capabilities'])) {
            $warnings[] = 'Field "capabilities" is missing — consider declaring A2A capabilities (streaming, pushNotifications)';
        }

        if (!empty($warnings)) {
            return new ConventionResult('degraded', $warnings);
        }

        return new ConventionResult('healthy', []);
    }
}
