// E2E: Admin agent deletion
// Tests that a disabled agent with unavailable health status can be deleted.
// Also cleans up any leftover fake test agents from previous runs.

const { execSync } = require('child_process');
const assert = require('assert');

const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const FAKE_AGENT = 'e2e-fake-unavailable-agent';
const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const PSQL = `docker exec ai-community-platform-postgres-1 psql -U app -d ai_community_platform -c`;

Feature('Admin: Agent Delete');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();

    // Auto-accept all confirm dialogs
    I.usePlaywrightTo('auto-accept dialogs', async ({ page }) => {
        page.on('dialog', async (dialog) => {
            await dialog.accept();
        });
    });
});

Scenario(
    'setup: register a fake unavailable agent for deletion test',
    async ({ I }) => {
        // Register fake agent via internal API
        const res = await I.sendPostRequest('/api/v1/internal/agents/register', JSON.stringify({
            name: FAKE_AGENT,
            version: '0.0.1',
            description: 'E2E test fake agent (should be deleted)',
        }), {
            'Content-Type': 'application/json',
            'X-Platform-Internal-Token': INTERNAL_TOKEN,
        });
        assert.equal(res.status, 200, `Expected 200, got ${res.status}`);

        // Set health_status to 'unavailable' so the delete button appears
        execSync(`${PSQL} "UPDATE agent_registry SET health_status = 'unavailable' WHERE name = '${FAKE_AGENT}'"`, {
            cwd: PROJECT_ROOT,
        });
    },
).tag('@admin').tag('@delete');

Scenario(
    'delete button is visible for disabled unavailable agent',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        agentsPage.seeAgent(FAKE_AGENT);
        agentsPage.seeAgentDisabled(FAKE_AGENT);
        agentsPage.seeDeleteButton(FAKE_AGENT);
    },
).tag('@admin').tag('@delete');

Scenario(
    'delete button is NOT visible for healthy agents',
    async ({ I, agentsPage }) => {
        await agentsPage.open();

        // Real agents (healthy) should not have the delete button
        const count = await I.grabNumberOfVisibleElements(
            '//tr[.//span[contains(@class,"badge-healthy")]]//button[contains(@class,"btn-delete")]',
        );
        assert.equal(count, 0, `Healthy agents must not show delete button, found ${count}`);
    },
).tag('@admin').tag('@delete');

Scenario(
    'clicking delete removes the fake agent from the registry',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        agentsPage.seeAgent(FAKE_AGENT);

        // Click delete (confirm is auto-accepted)
        await agentsPage.deleteAgent(FAKE_AGENT);

        // Wait for page reload
        await I.waitForElement('table', 10);

        // Agent should no longer appear
        I.dontSee(FAKE_AGENT, 'table');
    },
).tag('@admin').tag('@delete');

Scenario(
    'cleanup: remove any leftover test agents via SQL',
    async () => {
        // Clean up leftover test artifacts from functional and e2e tests
        execSync(
            `${PSQL} "DELETE FROM agent_registry WHERE name LIKE 'api-test-agent-%' OR name LIKE 'api-list-agent-%' OR name LIKE 'api-enable-agent-%' OR name LIKE 'e2e-fake-%' OR name = 'invalid-manifest-agent'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@delete');
