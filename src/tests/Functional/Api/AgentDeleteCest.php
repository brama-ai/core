<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Doctrine\DBAL\Connection;

/**
 * Functional tests for DELETE /api/v1/internal/agents/{name}.
 *
 * Verifies:
 * - Installed agent is uninstalled (installed_at cleared)
 * - Audit log entry with action 'uninstalled' is created
 * - Agent remains in registry (marketplace state)
 * - 404 for non-existent agent
 */
final class AgentDeleteCest
{
    private const INTERNAL_TOKEN = 'test-internal-token';

    private function login(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    /**
     * @return array<string, mixed>
     */
    private function validManifest(string $name): array
    {
        return [
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'Delete API test agent',
            'permissions' => ['admin'],
            'commands' => ['/test'],
            'events' => ['message.created'],
            'a2a_endpoint' => sprintf('http://%s/a2a', $name),
        ];
    }

    public function deleteNonExistentAgentReturns404(\FunctionalTester $I): void
    {
        $this->login($I);

        $I->sendDelete('/api/v1/internal/agents/non-existent-delete-agent-xyz');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
        $I->seeResponseContains('not found');
    }

    public function deleteInstalledAgentUninstallsAndClearsInstalledAt(\FunctionalTester $I): void
    {
        $name = 'api-delete-agent-'.bin2hex(random_bytes(4));

        // Register the agent
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);

        // Install the agent
        $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['status' => 'installed', 'name' => $name]);

        // Delete (uninstall) the agent
        $I->sendDelete(sprintf('/api/v1/internal/agents/%s', $name));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'uninstalled', 'name' => $name]);
    }

    public function deleteAgentCreatesAuditLogEntry(\FunctionalTester $I): void
    {
        $name = 'api-delete-audit-agent-'.bin2hex(random_bytes(4));

        // Register the agent
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);

        // Install the agent
        $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
        $I->seeResponseCodeIs(200);

        // Delete (uninstall) the agent
        $I->sendDelete(sprintf('/api/v1/internal/agents/%s', $name));
        $I->seeResponseCodeIs(200);

        // Verify audit log entry exists with action 'uninstalled'
        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');
        $auditAction = $connection->fetchOne(
            'SELECT action FROM agent_registry_audit WHERE agent_name = :name AND action = :action ORDER BY created_at DESC LIMIT 1',
            ['name' => $name, 'action' => 'uninstalled'],
        );
        $I->assertEquals('uninstalled', $auditAction, 'Expected audit log entry with action "uninstalled"');
    }

    public function deleteAgentRemainsInRegistryAsMarketplace(\FunctionalTester $I): void
    {
        $name = 'api-delete-marketplace-agent-'.bin2hex(random_bytes(4));

        // Register the agent
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);

        // Install the agent
        $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
        $I->seeResponseCodeIs(200);

        // Delete (uninstall) the agent
        $I->sendDelete(sprintf('/api/v1/internal/agents/%s', $name));
        $I->seeResponseCodeIs(200);

        // Verify agent still exists in registry (marketplace state: installed_at IS NULL)
        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');
        $installedAt = $connection->fetchOne(
            'SELECT installed_at FROM agent_registry WHERE name = :name',
            ['name' => $name],
        );
        $I->assertNull($installedAt, 'installed_at should be NULL after uninstall (marketplace state)');
    }

    public function deleteEnabledAgentReturnsConflict(\FunctionalTester $I): void
    {
        $name = 'api-delete-enabled-agent-'.bin2hex(random_bytes(4));

        // Register the agent
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);

        // Install and enable the agent
        $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
        $I->seeResponseCodeIs(200);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/enable', $name));
        $I->seeResponseCodeIs(200);

        // Attempt to delete an enabled agent — should return 409 Conflict
        $I->sendDelete(sprintf('/api/v1/internal/agents/%s', $name));
        $I->seeResponseCodeIs(409);
        $I->seeResponseIsJson();
        $I->seeResponseContains('enabled');
    }
}
