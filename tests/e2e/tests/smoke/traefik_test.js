// Smoke: Traefik routing
// Migrated from tests/traefik.spec.ts

const assert = require('assert');
const axios = require('axios');

const TRAEFIK_API = process.env.TRAEFIK_API || 'http://localhost:8080';

Feature('Smoke: Traefik');

/**
 * Check if Traefik API is available.
 */
async function isTraefikAvailable() {
    try {
        const res = await axios.get(`${TRAEFIK_API}/api/http/services`, { timeout: 3000 });
        return res.status === 200;
    } catch (_) {
        return false;
    }
}

Scenario('API is reachable', async ({ I }) => {
    if (!await isTraefikAvailable()) {
        I.say('SKIP: Traefik API not available at ' + TRAEFIK_API);
        return;
    }
    const res = await I.sendGetRequest(`${TRAEFIK_API}/api/http/services`);
    assert.strictEqual(res.status, 200);
    assert.ok(Array.isArray(res.data), 'Expected array of services');
}).tag('@smoke');

Scenario('core@docker is registered as enabled service', async ({ I }) => {
    if (!await isTraefikAvailable()) {
        I.say('SKIP: Traefik API not available at ' + TRAEFIK_API);
        return;
    }
    const res = await I.sendGetRequest(`${TRAEFIK_API}/api/http/services`);
    const core = res.data.find((s) => s.name === 'core@docker');
    assert.ok(core, 'core@docker service must exist');
    assert.strictEqual(core.status, 'enabled');
}).tag('@smoke');

// Helper: fetch ALL Traefik services (handles pagination).
async function fetchAllServices(I) {
    let all = [];
    let page = 1;
    const perPage = 100;
    // eslint-disable-next-line no-constant-condition
    while (true) {
        const res = await I.sendGetRequest(`${TRAEFIK_API}/api/http/services?page=${page}&per_page=${perPage}`);
        assert.strictEqual(res.status, 200);
        const items = Array.isArray(res.data) ? res.data : [];
        all = all.concat(items);
        if (items.length < perPage) break;
        page++;
    }
    return all;
}

Scenario('knowledge-agent@docker is registered', async ({ I }) => {
    if (!await isTraefikAvailable()) {
        I.say('SKIP: Traefik API not available at ' + TRAEFIK_API);
        return;
    }
    const services = await fetchAllServices(I);
    const agent = services.find((s) => s.name.includes('knowledge-agent'));
    assert.ok(agent, 'knowledge-agent service must be registered in Traefik');
    assert.strictEqual(agent.status, 'enabled');
}).tag('@traefik');

Scenario('news-maker-agent@docker is registered', async ({ I }) => {
    if (!await isTraefikAvailable()) {
        I.say('SKIP: Traefik API not available at ' + TRAEFIK_API);
        return;
    }
    const services = await fetchAllServices(I);
    const agent = services.find((s) => s.name.includes('news-maker-agent'));
    assert.ok(agent, 'news-maker-agent service must be registered in Traefik');
    assert.strictEqual(agent.status, 'enabled');
}).tag('@traefik');

Scenario('hello-agent@docker is registered', async ({ I }) => {
    if (!await isTraefikAvailable()) {
        I.say('SKIP: Traefik API not available at ' + TRAEFIK_API);
        return;
    }
    const services = await fetchAllServices(I);
    const agent = services.find((s) => s.name.includes('hello-agent'));
    assert.ok(agent, 'hello-agent service must be registered in Traefik');
    assert.strictEqual(agent.status, 'enabled');
}).tag('@traefik');
