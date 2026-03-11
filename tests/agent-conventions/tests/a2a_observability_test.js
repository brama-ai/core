// TC-03: A2A Envelope + Correlation IDs
// Baseline conventions for request_id/trace_id behavior and structured responses.

const assert = require('node:assert/strict');

Feature('TC-03 A2A Envelope And Correlation');

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const HEX32_RE = /^[0-9a-f]{32}$/i;
const REQUEST_ID_RE = /^[a-zA-Z0-9][a-zA-Z0-9:_.-]{5,127}$/;

function isStructuredStatus(status) {
    return ['completed', 'failed', 'needs_clarification'].includes(status);
}

function isReasonableRequestId(value) {
    return UUID_RE.test(value) || REQUEST_ID_RE.test(value);
}

async function isHelloAgent(I) {
    const manifest = await I.sendGetRequest('/api/v1/manifest');
    return manifest?.data?.name === 'hello-agent';
}

Scenario('A2A preserves provided request_id and returns structured envelope', async ({ I }) => {
    if (!(await isHelloAgent(I))) {
        I.say('Skipping: scenario is specific to hello-agent intent contract');
        return;
    }

    const requestId = '00000000-0000-7000-8000-000000000001';
    const traceId = '00000000000000000000000000000001';

    assert.ok(UUID_RE.test(requestId), 'test request_id must be UUID');
    assert.ok(HEX32_RE.test(traceId), 'test trace_id must be 32-hex');

    const res = await I.sendPostRequest('/api/v1/a2a', {
        intent: 'hello.greet',
        payload: { name: 'ConventionAudit' },
        request_id: requestId,
        trace_id: traceId,
    });

    assert.strictEqual(res.status, 200, `Expected 200, got ${res.status}`);
    assert.ok(isStructuredStatus(res?.data?.status), 'status must be structured');
    assert.strictEqual(res?.data?.request_id, requestId, 'request_id must be preserved');
});

Scenario('A2A generates request_id when omitted', async ({ I }) => {
    if (!(await isHelloAgent(I))) {
        I.say('Skipping: scenario is specific to hello-agent intent contract');
        return;
    }

    const res = await I.sendPostRequest('/api/v1/a2a', {
        intent: 'hello.greet',
        payload: { name: 'NoRequestId' },
        trace_id: '00000000000000000000000000000002',
    });

    assert.strictEqual(res.status, 200, `Expected 200, got ${res.status}`);
    assert.ok(isStructuredStatus(res?.data?.status), 'status must be structured');
    assert.strictEqual(typeof res?.data?.request_id, 'string', 'request_id must be a string');
    assert.ok(
        isReasonableRequestId(res?.data?.request_id || ''),
        `generated request_id looks invalid: ${String(res?.data?.request_id)}`,
    );
});

Scenario('A2A rejects malformed envelope without intent', async ({ I }) => {
    if (!(await isHelloAgent(I))) {
        I.say('Skipping: scenario is specific to hello-agent intent contract');
        return;
    }

    const res = await I.sendPostRequest('/api/v1/a2a', {
        payload: { name: 'BrokenEnvelope' },
        request_id: 'req_missing_intent_001',
    });

    assert.ok([400, 422].includes(res.status), `Expected 400/422, got ${res.status}`);
});

Scenario('A2A returns structured failed response for unknown intent', async ({ I }) => {
    if (!(await isHelloAgent(I))) {
        I.say('Skipping: scenario is specific to hello-agent intent contract');
        return;
    }

    const requestId = 'req_unknown_intent_001';
    const res = await I.sendPostRequest('/api/v1/a2a', {
        intent: 'hello.unknown',
        payload: {},
        request_id: requestId,
        trace_id: '00000000000000000000000000000003',
    });

    assert.strictEqual(res.status, 200, `Expected 200, got ${res.status}`);
    assert.strictEqual(res?.data?.status, 'failed', 'unknown intent must return failed status');
    assert.strictEqual(res?.data?.request_id, requestId, 'request_id must be preserved');
    assert.strictEqual(typeof res?.data?.error, 'string', 'error must be a string');
});
