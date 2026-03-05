<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentInstaller;

use App\AgentInstaller\AgentInstallException;
use App\AgentInstaller\AgentMigrationTrigger;
use Codeception\Test\Unit;

final class AgentMigrationTriggerTest extends Unit
{
    public function testTriggerMigrationsReturnsWhenNoEndpointsProvided(): void
    {
        $trigger = new AgentMigrationTrigger('test-token');

        $trigger->triggerMigrations([
            'name' => 'no-endpoints-agent',
            'version' => '1.0.0',
        ]);

        $this->assertTrue(true);
    }

    public function testTriggerMigrationsFailsWhenHealthUrlIsUnreachable(): void
    {
        $trigger = new AgentMigrationTrigger('test-token');

        $this->expectException(AgentInstallException::class);
        $this->expectExceptionMessage('Migration trigger failed');

        $trigger->triggerMigrations([
            'name' => 'unreachable-health-agent',
            'version' => '1.0.0',
            'health_url' => 'http://127.0.0.1:9/health',
        ]);
    }

    public function testTriggerMigrationsFallsBackToA2aEndpoint(): void
    {
        $trigger = new AgentMigrationTrigger('test-token');

        $this->expectException(AgentInstallException::class);
        $this->expectExceptionMessage('Migration trigger failed');

        $trigger->triggerMigrations([
            'name' => 'a2a-fallback-agent',
            'version' => '1.0.0',
            'a2a_endpoint' => 'http://127.0.0.1:9/api/v1/a2a',
        ]);
    }
}

