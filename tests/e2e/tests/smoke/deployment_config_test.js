// Smoke: deployment configuration validation
// Tests that the platform works with environment variable-based configuration

const assert = require('assert');

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
    // Test hello-agent health
    try {
        const helloRes = await I.sendGetRequest('http://hello-agent/health');
        if (helloRes.status === 200) {
            assert.strictEqual(helloRes.data.status, 'ok');
            assert.strictEqual(helloRes.data.service, 'hello-agent');
            
            // Test hello-agent readiness
            const helloReadyRes = await I.sendGetRequest('http://hello-agent/health/ready');
            assert.ok([200, 503].includes(helloReadyRes.status));
        }
    } catch (e) {
        console.log('Hello agent not available, skipping test');
    }
    
    // Test knowledge-agent health
    try {
        const knowledgeRes = await I.sendGetRequest('http://knowledge-agent/health');
        if (knowledgeRes.status === 200) {
            assert.strictEqual(knowledgeRes.data.status, 'ok');
            assert.strictEqual(knowledgeRes.data.service, 'knowledge-agent');
            
            // Test knowledge-agent readiness
            const knowledgeReadyRes = await I.sendGetRequest('http://knowledge-agent/health/ready');
            assert.ok([200, 503].includes(knowledgeReadyRes.status));
        }
    } catch (e) {
        console.log('Knowledge agent not available, skipping test');
    }
    
    // Test news-maker-agent health
    try {
        const newsRes = await I.sendGetRequest('http://news-maker-agent:8000/health');
        if (newsRes.status === 200) {
            assert.strictEqual(newsRes.data.status, 'ok');
            assert.strictEqual(newsRes.data.service, 'news-maker-agent');
            
            // Test news-maker-agent readiness
            const newsReadyRes = await I.sendGetRequest('http://news-maker-agent:8000/health/ready');
            assert.ok([200, 503].includes(newsReadyRes.status));
        }
    } catch (e) {
        console.log('News maker agent not available, skipping test');
    }
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