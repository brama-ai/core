const assert = require('assert');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://localhost';

function readGatewayToken() {
    if (process.env.OPENCLAW_GATEWAY_TOKEN && process.env.OPENCLAW_GATEWAY_TOKEN.trim() !== '') {
        return process.env.OPENCLAW_GATEWAY_TOKEN.trim();
    }

    const envPath = path.resolve(process.cwd(), '../../docker/openclaw/.env');
    if (!fs.existsSync(envPath)) {
        throw new Error(`Gateway token not found: ${envPath} does not exist`);
    }

    const lines = fs.readFileSync(envPath, 'utf8').split('\n');
    const tokenLine = lines.find((line) => line.startsWith('OPENCLAW_GATEWAY_TOKEN='));
    if (!tokenLine) {
        throw new Error('OPENCLAW_GATEWAY_TOKEN is missing in docker/openclaw/.env');
    }

    const token = tokenLine.slice('OPENCLAW_GATEWAY_TOKEN='.length).trim();
    if (!token) {
        throw new Error('OPENCLAW_GATEWAY_TOKEN is empty');
    }

    return token;
}

Feature('OpenClaw: A2A Bridge Contract');

let gatewayToken;

Before(() => {
    gatewayToken = readGatewayToken();
});

async function sendMessageViaGateway(payload, timeoutMs = 35000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetch(`${BASE_URL}/api/v1/a2a/send-message`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${gatewayToken}`,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
            signal: controller.signal,
        });

        const text = await response.text();
        let data = {};
        try {
            data = JSON.parse(text);
        } catch {
            data = { raw: text };
        }

        return { status: response.status, data };
    } finally {
        clearTimeout(timer);
    }
}

Scenario('discovery without auth returns 401', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/a2a/discovery');

    assert.strictEqual(res.status, 401);
    assert.strictEqual(res.data.error, 'Unauthorized');
}).tag('@openclaw').tag('@a2a').tag('@p0');

Scenario('discovery with valid token returns tool catalog', async ({ I }) => {
    I.haveRequestHeaders({
        Authorization: `Bearer ${gatewayToken}`,
    });

    const res = await I.sendGetRequest('/api/v1/a2a/discovery');

    assert.strictEqual(res.status, 200);
    assert.ok(Array.isArray(res.data.tools), 'tools must be an array');
    assert.ok(res.data.tools.length > 0, 'tools must not be empty');
    assert.ok(
        res.data.tools.some((tool) => tool.name === 'hello.greet'),
        'tool catalog must include hello.greet',
    );
}).tag('@openclaw').tag('@a2a').tag('@p0');

Scenario('send-message with hello.greet returns structured gateway response', async () => {
    const res = await sendMessageViaGateway({
        tool: 'hello.greet',
        input: { name: 'E2E' },
        trace_id: 'trace_e2e_openclaw_1',
        request_id: 'req_e2e_openclaw_1',
    });

    assert.strictEqual(res.status, 200);
    assert.ok(['completed', 'failed', 'input_required'].includes(res.data.status), 'status must be structured');
    assert.strictEqual(res.data.tool, 'hello.greet');
    assert.ok(res.data.request_id, 'request_id must be present');
}).tag('@openclaw').tag('@a2a').tag('@p0');

Scenario('send-message with unknown tool returns failed reason', async ({ I }) => {
    I.haveRequestHeaders({
        Authorization: `Bearer ${gatewayToken}`,
        'Content-Type': 'application/json',
    });

    const res = await I.sendPostRequest('/api/v1/a2a/send-message', {
        tool: 'nonexistent.tool',
        input: {},
        trace_id: 'trace_e2e_openclaw_2',
        request_id: 'req_e2e_openclaw_2',
    });

    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.data.status, 'failed');
    assert.strictEqual(res.data.reason, 'unknown_tool');
    assert.strictEqual(res.data.trace_id, 'trace_e2e_openclaw_2');
    assert.strictEqual(res.data.request_id, 'req_e2e_openclaw_2');
}).tag('@openclaw').tag('@a2a').tag('@p0');
