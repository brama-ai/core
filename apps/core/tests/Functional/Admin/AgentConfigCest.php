<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

final class AgentConfigCest
{
    private function login(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    public function agentsPageContainsSettingsLink(\FunctionalTester $I): void
    {
        $this->login($I);

        $I->sendGet('/admin/agents');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('/settings');
        $I->seeResponseContains('Налаштування');
    }

    public function agentSettingsPageContainsConfigForm(\FunctionalTester $I): void
    {
        $this->login($I);

        $I->sendGet('/admin/agents/knowledge-agent/settings');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('configDescription');
        $I->seeResponseContains('configSystemPrompt');
        $I->seeResponseContains('Конфігурація');
    }

    public function configUpdateEndpointRequiresAuth(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPut('/api/v1/internal/agents/test-agent/config', json_encode([
            'description' => 'Test',
            'system_prompt' => 'Test prompt',
        ], JSON_THROW_ON_ERROR));
        $I->seeResponseContains('_username');
    }

    public function helloAgentConfigCanBeUpdatedAndSavedToDatabase(\FunctionalTester $I): void
    {
        // 1. Log in to the admin panel
        $this->login($I);

        // 2. Go to management -> agents and see the agent
        $I->sendGet('/admin/agents');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('hello-agent'); // Known as "hello-world" conceptually

        // 3. Click settings
        $I->sendGet('/admin/agents/hello-agent/settings');
        $I->seeResponseCodeIs(200);

        // 4. See description and system prompt (fields)
        $I->seeResponseContains('configDescription');
        $I->seeResponseContains('configSystemPrompt');

        // 5. Test API validation for these fields
        $I->haveHttpHeader('Content-Type', 'application/json');

        // Validation: Empty body
        $I->sendPut('/api/v1/internal/agents/hello-agent/config', '');
        $I->seeResponseCodeIs(422);
        $I->seeResponseContains('Empty request body');

        // Validation: Invalid JSON
        $I->sendPut('/api/v1/internal/agents/hello-agent/config', '{invalid_json}');
        $I->seeResponseCodeIs(422);
        $I->seeResponseContains('Invalid JSON');

        // 6. Test that they save data and write to their DB
        $validConfig = json_encode([
            'description' => 'Tested hello-world description',
            'system_prompt' => 'Tested hello-world system prompt',
        ], JSON_THROW_ON_ERROR);

        $I->sendPut('/api/v1/internal/agents/hello-agent/config', $validConfig);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('updated');

        // Verify in DB by reloading the settings page
        $I->sendGet('/admin/agents/hello-agent/settings');
        $I->seeResponseCodeIs(200);
        $I->seeResponseContains('Tested hello-world description');
        $I->seeResponseContains('Tested hello-world system prompt');
    }
}
