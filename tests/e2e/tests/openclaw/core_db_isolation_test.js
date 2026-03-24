const assert = require('assert');
const { execSync } = require('child_process');

const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'ai_community_platform_test';
const DEFAULT_CORE_DB_NAME = process.env.DEFAULT_CORE_DB_NAME || 'ai_community_platform';

function quoteLiteral(value) {
    return `'${String(value).replace(/'/g, "''")}'`;
}

function runSql(dbName, sql) {
    const escapedSql = sql.replace(/"/g, '\\"');

    return execSync(
        `docker exec brama-postgres-1 psql -U app -d ${dbName} -t -A -c "${escapedSql}"`,
        { encoding: 'utf8' },
    ).trim();
}

function countAgentByName(dbName, agentName) {
    const sql = `SELECT count(*) FROM agent_registry WHERE name = ${quoteLiteral(agentName)};`;
    return Number.parseInt(runSql(dbName, sql), 10);
}

function deleteAgentByName(dbName, agentName) {
    const sql = `DELETE FROM agent_registry WHERE name = ${quoteLiteral(agentName)};`;
    runSql(dbName, sql);
}

Feature('OpenClaw: Core DB Isolation');

Scenario('internal register writes into E2E core DB only', async ({ I }) => {
    const agentName = `e2e-dbcheck-${Date.now()}`;

    try {
        const res = await I.sendPostRequest(
            '/api/v1/internal/agents/register',
            JSON.stringify({
                name: agentName,
                version: '0.0.1',
                description: 'E2E DB isolation probe',
            }),
            {
                'Content-Type': 'application/json',
                'X-Platform-Internal-Token': INTERNAL_TOKEN,
            },
        );

        assert.strictEqual(res.status, 200);

        const defaultDbCount = countAgentByName(DEFAULT_CORE_DB_NAME, agentName);
        const e2eDbCount = countAgentByName(CORE_DB_NAME, agentName);

        assert.strictEqual(defaultDbCount, 0, 'default core DB must not contain E2E mutation');
        assert.strictEqual(e2eDbCount, 1, 'E2E core DB must contain the inserted agent row');
    } finally {
        deleteAgentByName(CORE_DB_NAME, agentName);
        deleteAgentByName(DEFAULT_CORE_DB_NAME, agentName);
    }
}).tag('@openclaw').tag('@config').tag('@p0').tag('@db-isolation');
