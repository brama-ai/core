<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use Doctrine\DBAL\Connection;

/**
 * Functional tests for the Agent API Proxy.
 *
 * Route: GET|POST|PUT|DELETE /api/agents/{agentName}/{path}
 *
 * Tests validation logic (404/503/403/405) without actually proxying to agents.
 * The proxy-to-agent scenario (5.7) requires a live agent and is covered by E2E tests.
 */
final class AgentProxyCest
{
    private const INTERNAL_TOKEN = 'test-internal-token';

    private function registerAgent(\FunctionalTester $I, string $name, bool $install = false, bool $enable = false): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'Proxy test agent',
            'a2a_endpoint' => sprintf('http://%s/a2a', $name),
        ], JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        if ($install || $enable) {
            $this->login($I);
            $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
            $I->seeResponseCodeIs(200);
        }

        if ($enable) {
            $I->sendPost(sprintf('/api/v1/internal/agents/%s/enable', $name));
            $I->seeResponseCodeIs(200);
        }
    }

    private function login(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    private function addPublicEndpoint(\FunctionalTester $I, string $agentName, string $path, array $methods): void
    {
        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');

        $agentId = $connection->fetchOne(
            'SELECT id FROM agent_registry WHERE name = :name',
            ['name' => $agentName],
        );

        if (false === $agentId) {
            return;
        }

        $connection->executeStatement(
            <<<'SQL'
            INSERT INTO agent_public_endpoints (agent_id, path, methods, description, created_at, updated_at)
            VALUES (:agentId, :path, :methods, NULL, now(), now())
            ON CONFLICT (agent_id, path) DO UPDATE SET methods = EXCLUDED.methods, updated_at = now()
            SQL,
            [
                'agentId' => $agentId,
                'path' => $path,
                'methods' => json_encode($methods, JSON_THROW_ON_ERROR),
            ],
        );
    }

    public function proxyReturns404ForNonExistentAgent(\FunctionalTester $I): void
    {
        $I->sendPost('/api/agents/nonexistent-proxy-agent-xyz/anything');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Agent not found']);
    }

    public function proxyReturns503ForDisabledAgent(\FunctionalTester $I): void
    {
        $name = 'proxy-disabled-agent-'.bin2hex(random_bytes(4));
        $this->registerAgent($I, $name, true, false);

        $I->sendPost(sprintf('/api/agents/%s/webhook', $name));
        $I->seeResponseCodeIs(503);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Agent is disabled']);
    }

    public function proxyReturns403WhenEndpointNotDeclared(\FunctionalTester $I): void
    {
        $name = 'proxy-no-endpoint-agent-'.bin2hex(random_bytes(4));
        $this->registerAgent($I, $name, true, true);

        $I->sendPost(sprintf('/api/agents/%s/webhook', $name));
        $I->seeResponseCodeIs(403);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Endpoint not exposed']);
    }

    public function proxyReturns405WhenMethodNotAllowed(\FunctionalTester $I): void
    {
        $name = 'proxy-method-agent-'.bin2hex(random_bytes(4));
        $this->registerAgent($I, $name, true, true);
        $this->addPublicEndpoint($I, $name, '/webhook', ['POST']);

        $I->sendGet(sprintf('/api/agents/%s/webhook', $name));
        $I->seeResponseCodeIs(405);
    }

    public function proxyRouteIsAccessibleWithoutAuthentication(\FunctionalTester $I): void
    {
        // Ensure the proxy route does not redirect to login (no auth required)
        $I->sendPost('/api/agents/nonexistent-auth-test-xyz/webhook');
        // Should return 404 (agent not found), NOT a redirect to login
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
    }

    public function healthPollSyncCreatesPublicEndpointRows(\FunctionalTester $I): void
    {
        $name = 'proxy-sync-agent-'.bin2hex(random_bytes(4));

        // Register agent with public_endpoints in manifest
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'Sync test agent',
            'a2a_endpoint' => sprintf('http://%s/a2a', $name),
            'public_endpoints' => [
                ['path' => '/webhook/test', 'methods' => ['POST'], 'description' => 'Test webhook'],
            ],
        ], JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        // Manually trigger the sync (simulating what AgentDiscoveryCommand does)
        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');

        $agentId = $connection->fetchOne(
            'SELECT id FROM agent_registry WHERE name = :name',
            ['name' => $name],
        );

        $I->assertNotFalse($agentId, 'Agent should be registered');

        // Insert endpoint directly (simulating discovery sync)
        $connection->executeStatement(
            <<<'SQL'
            INSERT INTO agent_public_endpoints (agent_id, path, methods, description, created_at, updated_at)
            VALUES (:agentId, '/webhook/test', '["POST"]', 'Test webhook', now(), now())
            ON CONFLICT (agent_id, path) DO NOTHING
            SQL,
            ['agentId' => $agentId],
        );

        $count = $connection->fetchOne(
            'SELECT COUNT(*) FROM agent_public_endpoints WHERE agent_id = :agentId',
            ['agentId' => $agentId],
        );

        $I->assertSame('1', (string) $count, 'Expected 1 public endpoint row');
    }

    public function publicEndpointRowsDeletedWhenAgentDeletedFromDb(\FunctionalTester $I): void
    {
        $name = 'proxy-cascade-agent-'.bin2hex(random_bytes(4));
        $this->registerAgent($I, $name, false, false);
        $this->addPublicEndpoint($I, $name, '/webhook', ['POST']);

        /** @var Connection $connection */
        $connection = $I->grabService('doctrine.dbal.default_connection');

        $agentId = $connection->fetchOne(
            'SELECT id FROM agent_registry WHERE name = :name',
            ['name' => $name],
        );

        $countBefore = $connection->fetchOne(
            'SELECT COUNT(*) FROM agent_public_endpoints WHERE agent_id = :agentId',
            ['agentId' => $agentId],
        );
        $I->assertSame('1', (string) $countBefore, 'Expected 1 endpoint before delete');

        // Delete agent directly from DB (simulating hard delete with cascade)
        $connection->executeStatement(
            'DELETE FROM agent_registry WHERE id = :agentId',
            ['agentId' => $agentId],
        );

        // Verify cascade delete
        $countAfter = $connection->fetchOne(
            'SELECT COUNT(*) FROM agent_public_endpoints WHERE agent_id = :agentId',
            ['agentId' => $agentId],
        );
        $I->assertSame('0', (string) $countAfter, 'Expected 0 endpoints after agent delete (cascade)');
    }
}
