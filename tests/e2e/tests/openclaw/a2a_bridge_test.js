const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const BASE_URL = process.env.BASE_URL || 'http://localhost:18080';
const KNOWLEDGE_DB_NAME = process.env.KNOWLEDGE_DB_NAME || 'knowledge_agent_test';
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'ai_community_platform_test';
const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';

function readGatewayToken() {
    if (process.env.OPENCLAW_GATEWAY_TOKEN && process.env.OPENCLAW_GATEWAY_TOKEN.trim() !== '') {
        return process.env.OPENCLAW_GATEWAY_TOKEN.trim();
    }

    // Try multiple possible locations for the .env file containing the token.
    // The repo root is four levels up from tests/e2e/tests/openclaw/.
    const candidates = [
        path.resolve(__dirname, '../../../../apps/core/.env'),
        path.resolve(__dirname, '../../../../docker/openclaw/.env'),
        path.resolve(process.cwd(), '../../docker/openclaw/.env'),
        path.resolve(process.cwd(), 'apps/core/.env'),
    ];

    for (const envPath of candidates) {
        if (!fs.existsSync(envPath)) continue;

        const lines = fs.readFileSync(envPath, 'utf8').split('\n');
        const tokenLine = lines.find((line) => line.startsWith('OPENCLAW_GATEWAY_TOKEN='));
        if (!tokenLine) continue;

        const token = tokenLine.slice('OPENCLAW_GATEWAY_TOKEN='.length).trim();
        if (token) return token;
    }

    throw new Error(
        'OPENCLAW_GATEWAY_TOKEN not found. Set the env var or ensure apps/core/.env exists. Searched: ' +
            candidates.join(', '),
    );
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

function quoteLiteral(value) {
    return `'${String(value).replace(/'/g, "''")}'`;
}

function runKnowledgeSql(sql) {
    const escapedSql = sql.replace(/"/g, '\\"');

    return execSync(
        `docker exec brama-postgres-1 psql -U app -d ${KNOWLEDGE_DB_NAME} -t -A -c "${escapedSql}"`,
        { encoding: 'utf8' },
    ).trim();
}

function runCoreSql(sql) {
    const escapedSql = sql.replace(/"/g, '\\"');

    return execSync(
        `docker exec brama-postgres-1 psql -U app -d ${CORE_DB_NAME} -t -A -c "${escapedSql}"`,
        { encoding: 'utf8' },
    ).trim();
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

Scenario('send-message with knowledge.store_message persists source message metadata', async ({ I }) => {
    const requestId = `req_e2e_store_${Date.now()}`;
    const traceId = `trace_e2e_store_${Date.now()}`;
    const messageId = `msg_e2e_store_${Date.now()}`;

    const registerResponse = await I.sendPostRequest(
        '/api/v1/internal/agents/register',
        JSON.stringify({
            name: 'knowledge-agent',
            version: '1.0.0',
            description: 'Knowledge base management and semantic search',
            url: 'http://knowledge-agent-e2e/api/v1/knowledge/a2a',
            admin_url: 'http://localhost:18083/admin/knowledge',
            skills: [
                { id: 'knowledge.search', name: 'Knowledge Search', description: 'Search the knowledge base' },
                { id: 'knowledge.upload', name: 'Knowledge Upload', description: 'Extract and store knowledge from messages' },
                { id: 'knowledge.store_message', name: 'Knowledge Store Message', description: 'Persist source messages with metadata' },
            ],
        }),
        {
            'Content-Type': 'application/json',
            'X-Platform-Internal-Token': INTERNAL_TOKEN,
        },
    );
    assert.strictEqual(registerResponse.status, 200, 'knowledge-agent register must succeed');

    runCoreSql("UPDATE agent_registry SET enabled = true, installed_at = now() WHERE name = 'knowledge-agent'");

    const res = await sendMessageViaGateway({
        tool: 'knowledge.store_message',
        input: {
            message: {
                platform: 'telegram',
                event_type: 'message_created',
                chat_id: '-100999000',
                message_id: messageId,
                text: 'E2E source message payload',
                author: {
                    id: 'user-e2e-1',
                    username: 'e2e_user',
                    display_name: 'E2E User',
                },
                sent_at: '2026-03-07T12:30:00Z',
            },
            metadata: {
                channel: 'telegram.main',
                topic: 'e2e',
            },
        },
        trace_id: traceId,
        request_id: requestId,
    });

    assert.strictEqual(res.status, 200);
    assert.strictEqual(res.data.status, 'completed');
    assert.strictEqual(res.data.tool, 'knowledge.store_message');
    assert.ok(res.data.result && res.data.result.id, 'result.id must be present');

    const countSql = `SELECT count(*) FROM knowledge_source_messages WHERE request_id = ${quoteLiteral(requestId)} AND message_id = ${quoteLiteral(messageId)};`;
    const count = Number.parseInt(runKnowledgeSql(countSql), 10);
    assert.strictEqual(count, 1, 'knowledge_source_messages must contain exactly one stored row');
}).tag('@openclaw').tag('@a2a').tag('@knowledge').tag('@store-message').tag('@p0');
