<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\AgentRegistry\AgentRegistryInterface;
use Doctrine\DBAL\Connection;

final class AgentHealthPollerCommandCest
{
    public function healthPollerCommandCleansUpStaleMarketplaceAgents(\FunctionalTester $I): void
    {
        /** @var Connection $connection */
        $connection = $I->grabService(Connection::class);

        $name = 'stale-marketplace-agent-'.bin2hex(random_bytes(4));

        // Insert a stale marketplace agent: never installed, 5 consecutive failures
        $connection->executeStatement(
            <<<'SQL'
            INSERT INTO agent_registry (name, version, manifest, config, enabled, health_status, health_check_failures, registered_at, updated_at)
            VALUES (:name, '1.0.0', '{}', '{}', FALSE, 'unavailable', 5, now(), now())
            SQL,
            ['name' => $name],
        );

        $I->runSymfonyConsoleCommand('app:agent-health-poll');

        // Agent should be deleted
        /** @var AgentRegistryInterface $registry */
        $registry = $I->grabService(AgentRegistryInterface::class);
        $agent = $registry->findByName($name);
        $I->assertNull($agent);

        // Audit entry should exist
        $audit = $connection->fetchOne(
            "SELECT id FROM agent_registry_audit WHERE agent_name = :name AND action = 'stale_deleted'",
            ['name' => $name],
        );
        $I->assertNotFalse($audit);
    }
}
