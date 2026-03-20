// Smoke: core platform health endpoint
// Migrated from tests/health.spec.ts

const assert = require('assert');

Feature('Smoke: Health Endpoint');

Scenario('returns 200 with ok status through Traefik @smoke', async ({ I }) => {
    const res = await I.sendGetRequest('/health');
    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.data.status, 'ok');
    assert.strictEqual(res.data.service, 'core-platform');
    assert.ok(res.data.timestamp, 'timestamp should be present');
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

Scenario('readiness endpoint validates dependencies @smoke', async ({ I }) => {
    const res = await I.sendGetRequest('/health/ready');
    // Should return 200 if all dependencies are healthy, 503 if not
    assert.ok([200, 503].includes(res.status), 'readiness endpoint should return 200 or 503');
    assert.strictEqual(res.data.service, 'core-platform');
    assert.ok(res.data.checks, 'checks object should be present');
    assert.ok(res.data.checks.database, 'database check should be present');
}).tag('@smoke');

Scenario('liveness endpoint is lightweight @smoke', async ({ I }) => {
    const res = await I.sendGetRequest('/health/live');
    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.data.status, 'ok');
    assert.strictEqual(res.data.service, 'core-platform');
    assert.ok(res.data.uptime, 'uptime should be present');
}).tag('@smoke');
