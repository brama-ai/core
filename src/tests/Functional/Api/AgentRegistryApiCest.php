<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class AgentRegistryApiCest
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
            'description' => 'Registry API test agent',
            'permissions' => ['admin'],
            'commands' => ['/test'],
            'events' => ['message.created'],
            'a2a_endpoint' => sprintf('http://%s/a2a', $name),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postgresManifestWithUnreachableMigration(string $name): array
    {
        $suffix = bin2hex(random_bytes(4));

        return [
            'name' => $name,
            'version' => '1.0.0',
            'description' => 'Registry API postgres install test agent',
            'permissions' => ['admin'],
            'commands' => ['/test'],
            'events' => ['message.created'],
            // Closed localhost port in core container => migration trigger fails fast.
            'a2a_endpoint' => 'http://127.0.0.1:9/a2a',
            'storage' => [
                'postgres' => [
                    'db_name' => sprintf('agent_install_%s', $suffix),
                    'user' => sprintf('agent_install_%s', $suffix),
                    'password' => sprintf('pwd_%s', $suffix),
                ],
            ],
        ];
    }

    public function registerAgentFailsWithoutToken(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest('no-token-agent'), JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error' => 'Unauthorized']);
    }

    public function registerAgentFailsWithInvalidManifest(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);

        $invalid = ['name' => 'INVALID NAME!', 'version' => 'not-semver'];

        $I->sendPost('/api/v1/internal/agents/register', json_encode($invalid, JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"Manifest validation failed"');
        $I->seeResponseContains('name');
    }

    public function registerAgentWithValidManifest(\FunctionalTester $I): void
    {
        $name = 'api-test-agent-'.bin2hex(random_bytes(4));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'registered', 'name' => $name]);
    }

    public function listAgentsRequiresAuthentication(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/internal/agents');
        $I->seeResponseContains('_username');
    }

    public function enableDisableAgentRequiresAuthentication(\FunctionalTester $I): void
    {
        // Ensure the client is not authenticated from a previous test
        $I->logout();

        $I->sendPost('/api/v1/internal/agents/some-agent/install');
        $I->seeResponseContains('_username');

        $I->logout();
        $I->sendPost('/api/v1/internal/agents/some-agent/enable');
        $I->seeResponseContains('_username');

        $I->logout();
        $I->sendPost('/api/v1/internal/agents/some-agent/disable');
        $I->seeResponseContains('_username');

        $I->logout();
        $I->sendPost('/api/v1/internal/agents/some-agent/crawl');
        $I->seeResponseContains('_username');
    }

    public function listAgentsAsAdminContainsRegisteredAgent(\FunctionalTester $I): void
    {
        $name = 'api-list-agent-'.bin2hex(random_bytes(4));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);
        $I->sendGet('/api/v1/internal/agents');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains($name);
    }

    public function enableAndDisableAgentAsAdmin(\FunctionalTester $I): void
    {
        $name = 'api-enable-agent-'.bin2hex(random_bytes(4));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'installed', 'name' => $name]);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/enable', $name));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'enabled', 'name' => $name]);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/disable', $name));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'disabled', 'name' => $name]);
    }

    public function enableRequiresInstallFirst(\FunctionalTester $I): void
    {
        $name = 'api-not-installed-agent-'.bin2hex(random_bytes(4));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/enable', $name));
        $I->seeResponseCodeIs(409);
        $I->seeResponseIsJson();
        $I->seeResponseContains('not installed');
    }

    public function installContinuesWhenMigrationTriggerFails(\FunctionalTester $I): void
    {
        $name = 'api-install-warning-agent-'.bin2hex(random_bytes(4));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->postgresManifestWithUnreachableMigration($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/install', $name));
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'installed', 'name' => $name]);
        $I->seeResponseContains('"warnings"');
        $I->seeResponseContains('Migration trigger failed (best effort)');
    }

    public function crawlTriggerReturnsConflictForNonNewsMakerAgent(\FunctionalTester $I): void
    {
        $name = 'api-crawl-unsupported-agent-'.bin2hex(random_bytes(4));

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', self::INTERNAL_TOKEN);
        $I->sendPost('/api/v1/internal/agents/register', json_encode($this->validManifest($name), JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(200);

        $this->login($I);

        $I->sendPost(sprintf('/api/v1/internal/agents/%s/crawl', $name));
        $I->seeResponseCodeIs(409);
        $I->seeResponseIsJson();
        $I->seeResponseContains('supported only for news-maker-agent');
    }
}
