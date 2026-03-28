// E2E: Hello Agent — admin discovery, settings page, and webview
// Verifies hello-agent appears healthy in the admin panel,
// the settings page has description + system_prompt fields,
// and the webview renders the greeting.

const assert = require('assert');
const axios = require('axios');

const HELLO_URL = process.env.HELLO_URL || 'http://localhost:18085';

/**
 * Check if the hello-agent is available.
 */
async function isHelloAgentAvailable() {
    try {
        const res = await axios.get(`${HELLO_URL}/health`, { timeout: 3000 });
        return res.status === 200;
    } catch (_) {
        return false;
    }
}

Feature('Admin: Hello Agent');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'hello-agent is present and healthy after discovery',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        agentsPage.seeAgentLike('hello-agent');
        agentsPage.seeAgentHealthyLike('hello-agent');
    },
).tag('@admin').tag('@hello');

Scenario(
    'hello-agent settings page has config form',
    async ({ I }) => {
        I.amOnPage('/admin/agents/hello-agent/settings');
        await I.waitForElement('#configDescription', 10);

        I.see('Конфігурація');
        I.seeElement('#configDescription');
        I.seeElement('#configSystemPrompt');
    },
).tag('@admin').tag('@hello');

Scenario(
    'saving config persists description and system_prompt',
    async ({ I }) => {
        I.amOnPage('/admin/agents/hello-agent/settings');
        await I.waitForElement('#configDescription', 10);

        // Fill in fields
        I.clearField('#configDescription');
        I.fillField('#configDescription', 'E2E test description');
        I.clearField('#configSystemPrompt');
        I.fillField('#configSystemPrompt', 'E2E test system prompt');

        // Submit and wait for success
        I.click('button[type="submit"]');
        await I.waitForText('Збережено', 5);

        // Reload and verify values persisted
        I.amOnPage('/admin/agents/hello-agent/settings');
        await I.waitForElement('#configDescription', 10);

        I.seeInField('#configDescription', 'E2E test description');
        I.seeInField('#configSystemPrompt', 'E2E test system prompt');
    },
).tag('@admin').tag('@hello');

Scenario(
    'agents list shows Налаштування link for hello-agent',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        I.seeElement(
            '//tr[@data-agent-name="hello-agent"]//a[contains(text(),"Налаштування")]',
        );
    },
).tag('@admin').tag('@hello');

Scenario(
    'hello-agent webview renders greeting on port 8085',
    async ({ I }) => {
        if (!await isHelloAgentAvailable()) {
            I.say('SKIP: hello-agent not available at ' + HELLO_URL);
            return;
        }
        await I.ensureEdgeAccess(`${HELLO_URL}/`);
        I.amOnPage(`${HELLO_URL}/`);
        await I.waitForText('Hello, World!', 5);
        I.see('Hello, World!');
    },
).tag('@hello');

Scenario(
    'hello-agent health endpoint returns ok via Traefik',
    async ({ I }) => {
        if (!await isHelloAgentAvailable()) {
            I.say('SKIP: hello-agent not available at ' + HELLO_URL);
            return;
        }
        const cookieName = process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';
        await I.ensureEdgeAccess(`${HELLO_URL}/health`);
        const edgeCookie = await I.grabCookie(cookieName);
        if (!edgeCookie || !edgeCookie.value) {
            throw new Error(`Expected ${cookieName} cookie for health request`);
        }
        I.haveRequestHeaders({ Cookie: `${cookieName}=${edgeCookie.value}` });

        const response = await I.sendGetRequest(
            `${HELLO_URL}/health`,
        );
        assert.strictEqual(response.status, 200);
        assert.strictEqual(response.data.status, 'ok');
        assert.strictEqual(response.data.service, 'hello-agent');
    },
).tag('@smoke').tag('@hello');

Scenario(
    'hello-agent manifest is valid via Traefik',
    async ({ I }) => {
        if (!await isHelloAgentAvailable()) {
            I.say('SKIP: hello-agent not available at ' + HELLO_URL);
            return;
        }
        const cookieName = process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';
        await I.ensureEdgeAccess(`${HELLO_URL}/api/v1/manifest`);
        const edgeCookie = await I.grabCookie(cookieName);
        if (!edgeCookie || !edgeCookie.value) {
            throw new Error(`Expected ${cookieName} cookie for manifest request`);
        }
        I.haveRequestHeaders({ Cookie: `${cookieName}=${edgeCookie.value}` });

        const response = await I.sendGetRequest(
            `${HELLO_URL}/api/v1/manifest`,
        );
        assert.strictEqual(response.status, 200);
        assert.strictEqual(response.data.name, 'hello-agent');
        assert.strictEqual(response.data.version, '1.0.0');
        assert.ok(Array.isArray(response.data.skills), 'skills must be an array');
        assert.ok(
            response.data.skills.some((s) => s.id === 'hello.greet'),
            'skills must contain hello.greet',
        );
        assert.ok(
            response.data.skills.some((s) => s.id === 'hello.greet_me'),
            'skills must contain hello.greet_me',
        );
    },
).tag('@smoke').tag('@hello');
