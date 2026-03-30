// E2E: Admin agent violations modal
// Tests that agents with convention violations show a degraded badge and
// that clicking the badge opens the violations modal with violation details.
// UC: CUJ-20 — Admin views convention violation details via modal.

const { execSync } = require('child_process');
const assert = require('assert');

const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const FAKE_AGENT = 'e2e-fake-violations-agent';
const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'brama_test';
const POSTGRES_DSN = process.env.POSTGRES_DSN || `postgresql://app:app@localhost:5432/${CORE_DB_NAME}`;
const PSQL = process.env.PSQL_CMD || `psql ${POSTGRES_DSN} -c`;

Feature('Admin: Agent Violations Modal');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'setup: register fake agent and inject violations via SQL',
    async ({ I }) => {
        // Register the agent via API
        const registerResponse = await I.sendPostRequest('/api/v1/internal/agents/register', JSON.stringify({
            name: FAKE_AGENT,
            version: '0.0.1',
            description: 'E2E fake agent with violations',
            url: `http://${FAKE_AGENT}/api/v1/a2a`,
            skills: [],
        }), {
            'Content-Type': 'application/json',
            'X-Platform-Internal-Token': INTERNAL_TOKEN,
        });
        assert.equal(registerResponse.status, 200, `Expected 200, got ${registerResponse.status}`);

        // Inject violations and set health_status to degraded via SQL (pipe to avoid shell escaping issues)
        const violations = JSON.stringify(['Missing /health endpoint', 'Missing Docker label ai.platform.agent']);
        const sql = `UPDATE agent_registry SET health_status = 'degraded', violations = '${violations.replace(/'/g, "''")}' WHERE name = '${FAKE_AGENT}'`;
        execSync(
            `echo ${JSON.stringify(sql)} | psql ${POSTGRES_DSN}`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@violations');

Scenario(
    'agent with violations shows degraded badge in agents list',
    async ({ I, agentsPage }) => {
        await agentsPage.open();

        // Agent may be in marketplace tab (not installed)
        await agentsPage.switchToMarketplace();

        // Verify degraded badge is visible for the fake agent
        I.seeElement(`//tr[@data-agent-name="${FAKE_AGENT}"]//span[contains(@class,"badge-degraded")]`);
    },
).tag('@admin').tag('@violations');

Scenario(
    'clicking degraded badge opens violations modal with violation text',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        await agentsPage.switchToMarketplace();

        // Click the degraded badge to open violations modal
        I.click(`//tr[@data-agent-name="${FAKE_AGENT}"]//span[contains(@class,"badge-degraded")]`);

        // Verify modal is visible
        await I.waitForElement('#violationsModal.active', 5);

        // Verify violation text is displayed in the modal
        I.see('Missing /health endpoint', '#violationsModal');
        I.see('Missing Docker label', '#violationsModal');
    },
).tag('@admin').tag('@violations');

Scenario(
    'cleanup: remove fake violations test agent',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM agent_registry WHERE name = '${FAKE_AGENT}'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@violations');
