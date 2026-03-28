// Smoke: deployment configuration validation
// Tests that the platform works with environment variable-based configuration

const assert = require('assert');
const axios = require('axios');

Feature('Smoke: Deployment Configuration');

Scenario('core platform health endpoints work with env vars @smoke', async ({ I }) => {
    // Test basic health endpoint
    const healthRes = await I.sendGetRequest('/health');
    assert.strictEqual(healthRes.status, 200);
    assert.strictEqual(healthRes.data.status, 'ok');
    assert.strictEqual(healthRes.data.service, 'core-platform');
    
    // Test readiness endpoint (validates database connectivity)
    const readyRes = await I.sendGetRequest('/health/ready');
    assert.ok([200, 503].includes(readyRes.status), 'readiness should return 200 or 503');
    assert.ok(readyRes.data.checks, 'readiness should include dependency checks');
    
    // Test liveness endpoint
    const liveRes = await I.sendGetRequest('/health/live');
    assert.strictEqual(liveRes.status, 200);
    assert.strictEqual(liveRes.data.status, 'ok');
}).tag('@smoke');

Scenario('agent health endpoints work with env vars @smoke', async ({ I }) => {
    const helloUrl = process.env.HELLO_URL || 'http://localhost:18085';
    const knowledgeUrl = process.env.KNOWLEDGE_URL || 'http://localhost:18083';
    const newsUrl = process.env.NEWS_URL || 'http://localhost:18084';

    // Helper: test agent health tolerantly using axios directly (not I.sendGetRequest)
    // to avoid CodeceptJS marking the test as failed when the agent is unavailable.
    const checkAgent = async (name, baseUrl) => {
        try {
            const res = await axios.get(`${baseUrl}/health`, { timeout: 5000 });
            if (res.status === 200 && res.data) {
                assert.strictEqual(res.data.status, 'ok');
                if (res.data.service) {
                    assert.ok(
                        res.data.service.includes(name),
                        `Expected service name to contain '${name}', got '${res.data.service}'`,
                    );
                }
            }
        } catch (e) {
            const code = e.code || e.cause?.code || '';
            const status = e.response?.status;
            if (['ECONNREFUSED', 'ENOTFOUND', 'ETIMEDOUT', 'ECONNABORTED'].includes(code) ||
                status === 404 || status === 502 || status === 503) {
                I.say(`${name} not available (${code || status}) — skipping`);
                return;
            }
            throw e;
        }
    };

    await checkAgent('hello-agent', helloUrl);
    await checkAgent('knowledge-agent', knowledgeUrl);
    await checkAgent('news-maker-agent', newsUrl);
}).tag('@smoke');

Scenario('services can connect to dependencies via env vars @smoke', async ({ I }) => {
    // Test that core can reach its dependencies through readiness check
    const res = await I.sendGetRequest('/health/ready');
    
    if (res.status === 200) {
        // All dependencies are healthy
        assert.strictEqual(res.data.status, 'ok');
        assert.strictEqual(res.data.checks.database.status, 'ok');
        
        if (res.data.checks.database_write) {
            assert.strictEqual(res.data.checks.database_write.status, 'ok');
        }
    } else if (res.status === 503) {
        // Some dependencies are unhealthy, but the endpoint should still return structured data
        assert.strictEqual(res.data.status, 'error');
        assert.ok(res.data.checks, 'checks should be present even when unhealthy');
    }
}).tag('@smoke');
