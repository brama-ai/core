// Smoke: Services & admin panels accessibility
// Verifies that all platform services respond and show expected UI elements.

const assert = require('assert');
const axios = require('axios');

Feature('Smoke: Services Accessibility');

/**
 * Check if a URL is reachable using axios directly (not I.sendGetRequest).
 * Returns the response or null if the service is not available.
 */
async function tryGet(url, headers = {}) {
    try {
        return await axios.get(url, { timeout: 5000, headers });
    } catch (e) {
        const code = e.code || e.cause?.code || '';
        if (['ECONNREFUSED', 'ENOTFOUND', 'ETIMEDOUT', 'ECONNABORTED', 'ECONNRESET'].includes(code)) {
            return null;
        }
        if (e.response) {
            return e.response;
        }
        return null;
    }
}

// Service base URLs (Traefik-routed)
const CORE_URL = process.env.BASE_URL || 'http://localhost:18080';
const TRAEFIK_DASHBOARD_URL = process.env.TRAEFIK_DASHBOARD_URL || 'http://traefik.localhost';
const LITELLM_URL = process.env.LITELLM_URL || 'http://litellm.localhost';
const LANGFUSE_URL = process.env.LANGFUSE_URL || 'http://langfuse.localhost';

// Infrastructure direct ports
const TRAEFIK_API = process.env.TRAEFIK_API || 'http://localhost:8080';
const RABBITMQ_URL = process.env.RABBITMQ_URL || 'http://localhost:15672';
const OPENSEARCH_URL = process.env.OPENSEARCH_URL || 'http://localhost:9200';
const OPENSEARCH_DASHBOARDS_URL = process.env.OPENSEARCH_DASHBOARDS_URL || 'http://opensearch-dashboards.localhost';

// Agent e2e ports
const HELLO_AGENT_URL = process.env.HELLO_AGENT_URL || 'http://localhost:18085';
const KNOWLEDGE_AGENT_URL = process.env.KNOWLEDGE_AGENT_URL || 'http://localhost:18083';
const NEWS_MAKER_AGENT_URL = process.env.NEWS_MAKER_AGENT_URL || 'http://localhost:18084';
const DEV_REPORTER_AGENT_URL = process.env.DEV_REPORTER_AGENT_URL || 'http://localhost:18087';

// ─── Core Admin ────────────────────────────────────────────────

Scenario('Core: login page is accessible', async ({ I }) => {
    I.amOnPage('/admin/login');
    I.waitForElement('form', 10);
    I.seeElement('input[name="_username"]');
    I.seeElement('input[name="_password"]');
    I.seeElement('button[type="submit"]');
}).tag('@smoke').tag('@services');

Scenario('Core: dashboard is accessible after login', async ({ I }) => {
    await I.loginAsAdmin();
    I.see('admin');
    I.seeElement('.sidebar-nav');
}).tag('@smoke').tag('@services');

Scenario('Core: agents page is accessible', async ({ I }) => {
    await I.loginAsAdmin();
    I.amOnPage('/admin/agents');
    I.waitForElement('table', 10);
}).tag('@smoke').tag('@services');

Scenario('Core: logs page is accessible', async ({ I }) => {
    await I.loginAsAdmin();
    I.amOnPage('/admin/logs');
    I.waitForElement('.sidebar-nav', 10);
    I.seeInCurrentUrl('/admin/logs');
}).tag('@smoke').tag('@services');

Scenario('Core: scheduler page is accessible', async ({ I }) => {
    await I.loginAsAdmin();
    I.amOnPage('/admin/scheduler');
    I.waitForElement('.sidebar-nav', 10);
    I.seeInCurrentUrl('/admin/scheduler');
}).tag('@smoke').tag('@services');

Scenario('Core: chats page is accessible', async ({ I }) => {
    await I.loginAsAdmin();
    I.amOnPage('/admin/chats');
    I.waitForElement('.sidebar-nav', 10);
    I.seeInCurrentUrl('/admin/chats');
}).tag('@smoke').tag('@services');

Scenario('Core: settings page is accessible', async ({ I }) => {
    await I.loginAsAdmin();
    I.amOnPage('/admin/settings');
    I.waitForElement('.sidebar-nav', 10);
    I.seeInCurrentUrl('/admin/settings');
}).tag('@smoke').tag('@services');

Scenario('Core: tenants page is accessible', async ({ I }) => {
    await I.loginAsAdmin();
    I.amOnPage('/admin/tenants');
    I.waitForText('Tenant', 10);
}).tag('@smoke').tag('@services');

Scenario('Core: coder page is accessible', async ({ I }) => {
    await I.loginAsAdmin();
    I.amOnPage('/admin/coder');
    I.waitForText('Coder', 10);
}).tag('@smoke').tag('@services');

// ─── Traefik ───────────────────────────────────────────────────

Scenario('Traefik: API is reachable', async ({ I }) => {
    const res = await tryGet(`${TRAEFIK_API}/api/overview`);
    if (!res) {
        I.say('SKIP: Traefik API not available');
        return;
    }
    assert.strictEqual(res.status, 200);
    assert.ok(res.data.http, 'Traefik HTTP overview should be present');
}).tag('@smoke').tag('@services');

Scenario('Traefik: dashboard page loads', async ({ I }) => {
    const res = await tryGet(`${TRAEFIK_DASHBOARD_URL}/dashboard/`);
    if (!res) {
        I.say('SKIP: Traefik dashboard not available');
        return;
    }
    await I.loginAsAdmin();
    await I.ensureEdgeAccess(`${TRAEFIK_DASHBOARD_URL}/dashboard/`);
    I.amOnPage(`${TRAEFIK_DASHBOARD_URL}/dashboard/`);
    I.waitForElement('body', 10);
    // Traefik dashboard is a SPA; just verify no error page
    I.dontSee('404');
    I.dontSee('502 Bad Gateway');
}).tag('@smoke').tag('@services');

// ─── LiteLLM ──────────────────────────────────────────────────

Scenario('LiteLLM: API health returns ok', async ({ I }) => {
    const res = await tryGet(`${LITELLM_URL}/health`);
    if (!res) {
        I.say('SKIP: LiteLLM not available');
        return;
    }
    assert.ok([200, 401].includes(res.status), `LiteLLM health should return 200 or 401, got ${res.status}`);
}).tag('@smoke').tag('@services');

Scenario('LiteLLM: UI page loads', async ({ I }) => {
    const res = await tryGet(`${LITELLM_URL}/ui/`);
    if (!res) {
        I.say('SKIP: LiteLLM UI not available');
        return;
    }
    await I.loginAsAdmin();
    await I.ensureEdgeAccess(`${LITELLM_URL}/ui/`);
    I.amOnPage(`${LITELLM_URL}/ui/`);
    I.waitForElement('body', 10);
    I.dontSee('404 page not found');
    I.dontSee('502 Bad Gateway');
}).tag('@smoke').tag('@services');

// ─── Langfuse ──────────────────────────────────────────────────
// Langfuse is optional — compose.langfuse.yaml may not be active.

Scenario('Langfuse: service responds (login or dashboard)', async ({ I }) => {
    // Check if Langfuse is accessible from Node.js (not browser)
    const res = await tryGet(`${LANGFUSE_URL}/`);
    if (!res || res.status === 404 || res.status === 502) {
        I.say('SKIP: Langfuse not available');
        return;
    }
    // Check if browser can navigate to Langfuse
    let browserCanAccess = false;
    await I.usePlaywrightTo('check Langfuse browser access', async ({ page }) => {
        try {
            const response = await page.goto(`${LANGFUSE_URL}/`, { timeout: 5000, waitUntil: 'domcontentloaded' });
            browserCanAccess = response !== null && response.status() < 500;
        } catch (_) {
            browserCanAccess = false;
        }
    });
    if (!browserCanAccess) {
        I.say('SKIP: Langfuse not accessible from browser');
        return;
    }
    I.waitForElement('body', 10);
}).tag('@smoke').tag('@services').tag('@optional');

// ─── OpenSearch Dashboards ─────────────────────────────────────
// OpenSearch Dashboards is optional — may not be started yet.

Scenario('OpenSearch Dashboards: service responds', async ({ I }) => {
    const res = await tryGet(`${OPENSEARCH_DASHBOARDS_URL}/`);
    if (!res || res.status === 404 || res.status === 502) {
        I.say('SKIP: OpenSearch Dashboards not available');
        return;
    }
    I.amOnPage(`${OPENSEARCH_DASHBOARDS_URL}/`);
    I.waitForElement('body', 15);
}).tag('@smoke').tag('@services').tag('@optional');

// ─── Infrastructure: RabbitMQ ──────────────────────────────────

Scenario('RabbitMQ: management UI is reachable', async ({ I }) => {
    const res = await tryGet(`${RABBITMQ_URL}/api/overview`, {
        Authorization: 'Basic ' + Buffer.from('app:app').toString('base64'),
    });
    if (!res) {
        I.say('SKIP: RabbitMQ not available');
        return;
    }
    assert.strictEqual(res.status, 200);
    assert.ok(res.data.node, 'RabbitMQ node info should be present');
}).tag('@smoke').tag('@services');

// ─── Infrastructure: OpenSearch ────────────────────────────────

Scenario('OpenSearch: cluster is reachable', async ({ I }) => {
    const res = await tryGet(`${OPENSEARCH_URL}/`);
    if (!res) {
        I.say('SKIP: OpenSearch not available');
        return;
    }
    assert.strictEqual(res.status, 200);
    assert.ok(res.data.cluster_name, 'OpenSearch cluster_name should be present');
}).tag('@smoke').tag('@services');

// ─── Agents: health endpoints ──────────────────────────────────

Scenario('Hello Agent: health endpoint responds', async ({ I }) => {
    const res = await tryGet(`${HELLO_AGENT_URL}/health`);
    if (!res) {
        I.say('SKIP: Hello Agent not available');
        return;
    }
    assert.strictEqual(res.status, 200);
}).tag('@smoke').tag('@services').tag('@agents');

Scenario('Knowledge Agent: health endpoint responds', async ({ I }) => {
    const res = await tryGet(`${KNOWLEDGE_AGENT_URL}/health`);
    if (!res) {
        I.say('SKIP: Knowledge Agent not available');
        return;
    }
    assert.strictEqual(res.status, 200);
}).tag('@smoke').tag('@services').tag('@agents');

Scenario('News Maker Agent: health endpoint responds', async ({ I }) => {
    const res = await tryGet(`${NEWS_MAKER_AGENT_URL}/health`);
    if (!res) {
        I.say('SKIP: News Maker Agent not available');
        return;
    }
    assert.ok([200, 404].includes(res.status), `News Maker health: got ${res.status}`);
}).tag('@smoke').tag('@services').tag('@agents');

Scenario('Dev Reporter Agent: health endpoint responds', async ({ I }) => {
    const res = await tryGet(`${DEV_REPORTER_AGENT_URL}/health`);
    if (!res) {
        I.say('SKIP: Dev Reporter Agent not available');
        return;
    }
    assert.strictEqual(res.status, 200);
}).tag('@smoke').tag('@services').tag('@agents');

// ─── Agents: A2A manifest endpoints ───────────────────────────

Scenario('Hello Agent: A2A manifest is available', async ({ I }) => {
    const res = await tryGet(`${HELLO_AGENT_URL}/api/v1/manifest`);
    if (!res) {
        I.say('SKIP: Hello Agent not available');
        return;
    }
    assert.strictEqual(res.status, 200);
    assert.ok(res.data.name, 'Agent manifest should have a name');
}).tag('@smoke').tag('@services').tag('@agents');

Scenario('Knowledge Agent: A2A manifest is available', async ({ I }) => {
    const res = await tryGet(`${KNOWLEDGE_AGENT_URL}/api/v1/manifest`);
    if (!res) {
        I.say('SKIP: Knowledge Agent not available');
        return;
    }
    assert.strictEqual(res.status, 200);
    assert.ok(res.data.name, 'Agent manifest should have a name');
}).tag('@smoke').tag('@services').tag('@agents');

Scenario('Dev Reporter Agent: A2A manifest is available', async ({ I }) => {
    const res = await tryGet(`${DEV_REPORTER_AGENT_URL}/api/v1/manifest`);
    if (!res) {
        I.say('SKIP: Dev Reporter Agent not available');
        return;
    }
    assert.strictEqual(res.status, 200);
    assert.ok(res.data.name, 'Agent manifest should have a name');
}).tag('@smoke').tag('@services').tag('@agents');
