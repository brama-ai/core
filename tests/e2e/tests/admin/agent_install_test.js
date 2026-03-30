// E2E: Admin agent install flow
// Tests that a marketplace agent can be installed via the UI, transitioning
// from not_installed → disabled status and moving to the Installed tab.
// UC: CUJ-19 — Admin installs a marketplace agent via the UI.

const { execSync } = require('child_process');
const assert = require('assert');

const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const FAKE_AGENT = 'e2e-fake-install-agent';
const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'brama_test';
const POSTGRES_DSN = process.env.POSTGRES_DSN || `postgresql://app:app@localhost:5432/${CORE_DB_NAME}`;
const PSQL = process.env.PSQL_CMD || `psql ${POSTGRES_DSN} -c`;

Feature('Admin: Agent Install Flow');

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
    'setup: register fake marketplace agent via API',
    async ({ I }) => {
        const registerResponse = await I.sendPostRequest('/api/v1/internal/agents/register', JSON.stringify({
            name: FAKE_AGENT,
            version: '0.1.0',
            description: 'E2E fake agent for install flow test',
            url: `http://${FAKE_AGENT}/api/v1/a2a`,
            skills: [
                { id: 'fake.install', name: 'Fake Install', description: 'Test install skill' },
            ],
        }), {
            'Content-Type': 'application/json',
            'X-Platform-Internal-Token': INTERNAL_TOKEN,
        });
        assert.equal(registerResponse.status, 200, `Expected 200, got ${registerResponse.status}`);

        // Ensure agent is in marketplace state (not installed)
        execSync(
            `${PSQL} "UPDATE agent_registry SET installed_at = NULL, enabled = false WHERE name = '${FAKE_AGENT}'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@install');

Scenario(
    'fake agent appears in Marketplace tab with Install button',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        await agentsPage.switchToMarketplace();

        agentsPage.seeAgentLike(FAKE_AGENT);
        agentsPage.seeInstallButton(FAKE_AGENT);
    },
).tag('@admin').tag('@install');

Scenario(
    'clicking Install moves agent from Marketplace to Installed tab with disabled status',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        await agentsPage.switchToMarketplace();

        agentsPage.seeAgentLike(FAKE_AGENT);

        // Click Install via page object (exercises installAgent end-to-end)
        await agentsPage.installAgent(FAKE_AGENT);

        // Wait for page reload after install
        await I.waitForElement('table', 10);

        // Agent should no longer be in Marketplace tab
        await agentsPage.switchToMarketplace();
        I.dontSeeElement(`//div[@id="tab-marketplace" and contains(@class,"active")]//tr[@data-agent-name="${FAKE_AGENT}"]`);

        // Agent should now appear in Installed tab with disabled status
        await agentsPage.switchToInstalled();
        agentsPage.seeAgent(FAKE_AGENT);
        agentsPage.seeAgentDisabled(FAKE_AGENT);
    },
).tag('@admin').tag('@install');

Scenario(
    'cleanup: remove fake install test agent',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM agent_registry WHERE name = '${FAKE_AGENT}'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@install');
