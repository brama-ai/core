// Smoke: core platform health endpoint
// Migrated from tests/health.spec.ts

const assert = require('assert');

Feature('Smoke: Health Endpoint');

Scenario('returns 200 with ok status through Traefik @smoke', async ({ I }) => {
    const res = await I.sendGetRequest('/health');
    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.data.status, 'ok');
    assert.strictEqual(res.data.service, 'core-platform');
}).tag('@smoke');

Scenario('is accessible without authentication @smoke', async ({ I }) => {
    const res = await I.sendGetRequest('/health');
    assert.strictEqual(res.status, 200);
}).tag('@smoke');

Scenario('returns application/json content-type @smoke', async ({ I }) => {
    const res = await I.sendGetRequest('/health');
    assert.ok(
        res.headers['content-type'].includes('application/json'),
        'content-type must include application/json',
    );
}).tag('@smoke');
