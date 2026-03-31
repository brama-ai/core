<?php

declare(strict_types=1);

namespace App\Tests\Unit\A2AGateway;

use App\A2AGateway\AgentConventionVerifier;
use Codeception\Test\Unit;

final class AgentConventionVerifierTest extends Unit
{
    public function testPostgresAgentWithoutStartupMigrationReturnsError(): void
    {
        $verifier = new AgentConventionVerifier();

        $result = $verifier->verify([
            'name' => 'knowledge-agent',
            'version' => '1.0.0',
            'skills' => [],
            'capabilities' => [
                'streaming' => false,
                'pushNotifications' => false,
            ],
            'storage' => [
                'postgres' => [
                    'db_name' => 'knowledge_agent',
                    'user' => 'knowledge_agent',
                    'password' => 'knowledge_agent',
                ],
            ],
        ]);

        $this->assertSame('error', $result->status);
        $this->assertContains('Field "storage.postgres.startup_migration" is required for Postgres-backed agents', $result->violations);
    }

    public function testPostgresAgentWithStartupMigrationIsHealthy(): void
    {
        $verifier = new AgentConventionVerifier();

        $result = $verifier->verify([
            'name' => 'knowledge-agent',
            'version' => '1.0.0',
            'skills' => [],
            'capabilities' => [
                'streaming' => false,
                'pushNotifications' => false,
            ],
            'storage' => [
                'postgres' => [
                    'db_name' => 'knowledge_agent',
                    'user' => 'knowledge_agent',
                    'password' => 'knowledge_agent',
                    'startup_migration' => [
                        'enabled' => true,
                        'mode' => 'best_effort',
                        'command' => 'php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true',
                    ],
                ],
            ],
        ]);

        $this->assertSame('healthy', $result->status);
        $this->assertSame([], $result->violations);
    }

    public function testVerifyReturnsErrorWhenNameMissing(): void
    {
        $verifier = new AgentConventionVerifier();

        $result = $verifier->verify([
            'version' => '1.0.0',
            'skills' => [],
        ]);

        $this->assertSame('error', $result->status);
        $this->assertNotEmpty($result->violations);
        $this->assertContains('Required field missing or empty: name', $result->violations);
    }

    public function testVerifyReturnsErrorWhenVersionMissing(): void
    {
        $verifier = new AgentConventionVerifier();

        $result = $verifier->verify([
            'name' => 'my-agent',
            'skills' => [],
        ]);

        $this->assertSame('error', $result->status);
        $this->assertNotEmpty($result->violations);
        $this->assertContains('Required field missing or empty: version', $result->violations);
    }

    public function testVerifyReturnsDegradedWhenA2aEndpointMissingWithCapabilities(): void
    {
        $verifier = new AgentConventionVerifier();

        $result = $verifier->verify([
            'name' => 'my-agent',
            'version' => '1.0.0',
            'skills' => ['my-agent.do-something'],
            'capabilities' => [
                'streaming' => false,
                'pushNotifications' => false,
            ],
        ]);

        $this->assertSame('degraded', $result->status);
        $this->assertNotEmpty($result->violations);

        $hasEndpointWarning = false;
        foreach ($result->violations as $violation) {
            if (str_contains($violation, 'url') || str_contains($violation, 'a2a_endpoint')) {
                $hasEndpointWarning = true;
                break;
            }
        }
        $this->assertTrue($hasEndpointWarning, 'Expected a warning about missing url/a2a_endpoint when skills are declared');
    }

    public function testVerifyReturnsErrorForNullInput(): void
    {
        $verifier = new AgentConventionVerifier();

        $result = $verifier->verify(null);

        $this->assertSame('error', $result->status);
        $this->assertNotEmpty($result->violations);
    }

    public function testVerifyReturnsHealthyForValidManifest(): void
    {
        $verifier = new AgentConventionVerifier();

        $result = $verifier->verify([
            'name' => 'hello-agent',
            'version' => '1.0.0',
            'skills' => [],
            'capabilities' => [
                'streaming' => false,
                'pushNotifications' => false,
            ],
        ]);

        $this->assertSame('healthy', $result->status);
        $this->assertSame([], $result->violations);
    }

    public function testVerifyReturnsDegradedForNonSemverVersion(): void
    {
        $verifier = new AgentConventionVerifier();

        $result = $verifier->verify([
            'name' => 'my-agent',
            'version' => '1.0',
            'skills' => [],
            'capabilities' => [
                'streaming' => false,
                'pushNotifications' => false,
            ],
        ]);

        $this->assertSame('degraded', $result->status);
        $this->assertNotEmpty($result->violations);

        $hasVersionWarning = false;
        foreach ($result->violations as $violation) {
            if (str_contains($violation, 'version') && str_contains($violation, 'semver')) {
                $hasVersionWarning = true;
                break;
            }
        }
        $this->assertTrue($hasVersionWarning, 'Expected a warning about non-semver version string');
    }
}
