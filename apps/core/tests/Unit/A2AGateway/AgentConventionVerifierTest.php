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
}
