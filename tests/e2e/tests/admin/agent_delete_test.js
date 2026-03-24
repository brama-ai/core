// E2E: Admin agent uninstall/deprovision
// Tests that deleting an installed disabled agent moves it back to marketplace.

const { execSync } = require('child_process');
const assert = require('assert');

const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const FAKE_AGENT = 'e2e-fake-marketplace-agent';
const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'ai_community_platform_test';
const PSQL = `docker exec brama-postgres-1 psql -U app -d ${CORE_DB_NAME} -c`;

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
    'setup: register and mark fake agent as installed',
    async ({ I }) => {
        const registerResponse = await I.sendPostRequest('/api/v1/internal/agents/register', JSON.stringify({
            name: FAKE_AGENT,
            version: '0.0.1',
            description: 'E2E test fake agent',
            url: `http://${FAKE_AGENT}/api/v1/a2a`,
            skills: [
                { id: 'fake.echo', name: 'Fake Echo', description: 'Test skill' },
            ],
        }), {
            'Content-Type': 'application/json',
            'X-Platform-Internal-Token': INTERNAL_TOKEN,
        });
        assert.equal(registerResponse.status, 200, `Expected 200, got ${registerResponse.status}`);

        execSync(
            `${PSQL} "UPDATE agent_registry SET installed_at = now(), enabled = false, disabled_at = now() WHERE name = '${FAKE_AGENT}'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@delete');

Scenario(
    'delete button is visible for installed disabled agent',
    async ({ agentsPage }) => {
        await agentsPage.open();
        await agentsPage.switchToInstalled();
        agentsPage.seeAgent(FAKE_AGENT);
        agentsPage.seeAgentDisabled(FAKE_AGENT);
        agentsPage.seeDeleteButton(FAKE_AGENT);
    },
).tag('@admin').tag('@delete');

Scenario(
    'clicking delete moves agent from installed to marketplace',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        await agentsPage.switchToInstalled();
        agentsPage.seeAgent(FAKE_AGENT);

        await agentsPage.deleteAgent(FAKE_AGENT);
        await I.waitForElement('table', 10);

        await agentsPage.switchToInstalled();
        I.dontSeeElement(`//div[@id="tab-installed" and contains(@class,"active")]//tr[@data-agent-name="${FAKE_AGENT}"]`);

        await agentsPage.switchToMarketplace();
        agentsPage.seeAgent(FAKE_AGENT);
        agentsPage.seeInstallButton(FAKE_AGENT);
    },
).tag('@admin').tag('@delete');

Scenario(
    'cleanup: remove leftover test agents via SQL',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM agent_registry WHERE name LIKE 'api-test-agent-%' OR name LIKE 'api-list-agent-%' OR name LIKE 'api-enable-agent-%' OR name LIKE 'api-not-installed-agent-%' OR name LIKE 'e2e-fake-%' OR name = 'invalid-manifest-agent'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@delete');
