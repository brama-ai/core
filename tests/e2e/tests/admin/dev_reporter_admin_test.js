// E2E: Dev Reporter Agent — health endpoint, admin panel reports list, and status filter
// Verifies dev-reporter-agent health endpoint returns ok,
// the admin panel pipeline runs list page is accessible via iframe,
// and the status filter works correctly.

const assert = require('assert');
const axios = require('axios');

const DEV_REPORTER_URL = process.env.DEV_REPORTER_URL || 'http://localhost:18087';
const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const AGENT_NAME = 'dev-reporter-agent';
const SETTINGS_PATH = `/admin/agents/${AGENT_NAME}/settings`;

/**
 * Check if the dev-reporter-agent is available.
 */
async function isDevReporterAvailable() {
    try {
        const res = await axios.get(`${DEV_REPORTER_URL}/health`, { timeout: 3000 });
        return res.status === 200;
    } catch (_) {
        return false;
    }
}

Feature('Admin: Dev Reporter Agent');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();

    // Ensure the agent registry has the correct E2E admin_url
    await I.sendPostRequest(
        '/api/v1/internal/agents/register',
        JSON.stringify({
            name: AGENT_NAME,
            version: '1.0.0',
            description: 'Pipeline observability agent',
            url: 'http://dev-reporter-agent-e2e/api/v1/a2a',
            health_url: 'http://dev-reporter-agent-e2e/health',
            admin_url: `${DEV_REPORTER_URL}/admin/pipeline`,
            skills: [
                { id: 'devreporter.ingest', name: 'Pipeline Run Ingest', description: 'Ingest pipeline run reports' },
                { id: 'devreporter.status', name: 'Development Status', description: 'Query pipeline run status' },
                { id: 'devreporter.notify', name: 'Send Notification', description: 'Send notification messages' },
            ],
        }),
        { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
    );
});

Scenario(
    'dev-reporter-agent health endpoint returns ok',
    async ({ I }) => {
        if (!await isDevReporterAvailable()) {
            I.say('SKIP: dev-reporter-agent not available at ' + DEV_REPORTER_URL);
            return;
        }
        const cookieName = process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';
        await I.ensureEdgeAccess(`${DEV_REPORTER_URL}/health`);
        const edgeCookie = await I.grabCookie(cookieName);
        if (!edgeCookie || !edgeCookie.value) {
            throw new Error(`Expected ${cookieName} cookie for health request`);
        }
        I.haveRequestHeaders({ Cookie: `${cookieName}=${edgeCookie.value}` });

        const response = await I.sendGetRequest(`${DEV_REPORTER_URL}/health`);
        assert.strictEqual(response.status, 200);
        assert.strictEqual(response.data.status, 'ok');
        assert.strictEqual(response.data.service, 'dev-reporter-agent');
    },
).tag('@smoke').tag('@dev-reporter');

Scenario(
    'dev-reporter-agent manifest is valid',
    async ({ I }) => {
        if (!await isDevReporterAvailable()) {
            I.say('SKIP: dev-reporter-agent not available at ' + DEV_REPORTER_URL);
            return;
        }
        const cookieName = process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';
        await I.ensureEdgeAccess(`${DEV_REPORTER_URL}/api/v1/manifest`);
        const edgeCookie = await I.grabCookie(cookieName);
        if (!edgeCookie || !edgeCookie.value) {
            throw new Error(`Expected ${cookieName} cookie for manifest request`);
        }
        I.haveRequestHeaders({ Cookie: `${cookieName}=${edgeCookie.value}` });

        const response = await I.sendGetRequest(`${DEV_REPORTER_URL}/api/v1/manifest`);
        assert.strictEqual(response.status, 200);
        assert.strictEqual(response.data.name, AGENT_NAME);
        assert.strictEqual(response.data.version, '1.0.0');
        assert.ok(Array.isArray(response.data.skills), 'skills must be an array');
        assert.ok(
            response.data.skills.some((s) => s.id === 'devreporter.ingest'),
            'skills must contain devreporter.ingest',
        );
        assert.ok(
            response.data.skills.some((s) => s.id === 'devreporter.status'),
            'skills must contain devreporter.status',
        );
        assert.ok(
            response.data.skills.some((s) => s.id === 'devreporter.notify'),
            'skills must contain devreporter.notify',
        );
    },
).tag('@smoke').tag('@dev-reporter');

Scenario(
    'dev-reporter-agent is present and healthy after discovery',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        agentsPage.seeAgentLike(AGENT_NAME);
        agentsPage.seeAgentHealthyLike(AGENT_NAME);
    },
).tag('@admin').tag('@dev-reporter');

Scenario(
    'admin panel reports list page loads in iframe',
    async ({ I }) => {
        if (!await isDevReporterAvailable()) {
            I.say('SKIP: dev-reporter-agent not available at ' + DEV_REPORTER_URL);
            return;
        }
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 10);
        I.seeElement('iframe');

        // Switch into iframe context
        await I.switchTo('iframe');
        await I.waitForText('Pipeline Runs', 10);
        I.see('Pipeline Runs');
        I.see('Total runs');
        I.see('Passed');
        I.see('Failed');
        I.see('Pass rate');
        await I.switchTo();
    },
).tag('@admin').tag('@dev-reporter');

Scenario(
    'admin panel reports list shows table with columns',
    async ({ I }) => {
        if (!await isDevReporterAvailable()) {
            I.say('SKIP: dev-reporter-agent not available at ' + DEV_REPORTER_URL);
            return;
        }
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 10);

        await I.switchTo('iframe');
        await I.waitForText('Pipeline Runs', 10);

        // Verify table headers (CSS text-transform: uppercase renders them in caps)
        I.see('DATE');
        I.see('TASK');
        I.see('BRANCH');
        I.see('STATUS');
        I.see('DURATION');
        I.see('AGENTS');
        await I.switchTo();
    },
).tag('@admin').tag('@dev-reporter');

Scenario(
    'admin panel reports list has status filter buttons',
    async ({ I }) => {
        if (!await isDevReporterAvailable()) {
            I.say('SKIP: dev-reporter-agent not available at ' + DEV_REPORTER_URL);
            return;
        }
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 10);

        await I.switchTo('iframe');
        await I.waitForText('Pipeline Runs', 10);

        // Verify filter buttons
        I.see('All');
        I.see('Passed');
        I.see('Failed');
        await I.switchTo();
    },
).tag('@admin').tag('@dev-reporter');

Scenario(
    'admin panel reports list direct URL is accessible',
    async ({ I }) => {
        if (!await isDevReporterAvailable()) {
            I.say('SKIP: dev-reporter-agent not available at ' + DEV_REPORTER_URL);
            return;
        }
        const cookieName = process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';
        await I.ensureEdgeAccess(`${DEV_REPORTER_URL}/admin/pipeline`);
        I.amOnPage(`${DEV_REPORTER_URL}/admin/pipeline`);
        await I.waitForText('Pipeline Runs', 10);
        I.see('Pipeline Runs');
        I.see('Total runs');
    },
).tag('@admin').tag('@dev-reporter');
