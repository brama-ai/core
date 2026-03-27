// E2E: Admin log trace visualization
// Verifies the log trace page shows sequence diagram and waterfall view.
// UC: CUJ-19 — Log trace shows sequence diagram, spans, classic view.
//
// Logs are stored in OpenSearch (not PostgreSQL).
// Setup seeds a test trace via the OpenSearch bulk API;
// cleanup removes it by delete-by-query.

const { execSync } = require('child_process');

const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const OPENSEARCH_URL = process.env.OPENSEARCH_URL || 'http://opensearch:9200';
const TEST_TRACE_ID = 'e2e-test-trace-001';

// Build the index name for today (matches LogIndexManager::todayIndexName)
const today = new Date();
const INDEX_NAME = `platform_logs_${today.getFullYear()}_${String(today.getMonth() + 1).padStart(2, '0')}_${String(today.getDate()).padStart(2, '0')}`;

/**
 * Seed test trace documents into OpenSearch via docker compose exec + curl.
 *
 * The NDJSON bulk body is piped via stdin (docker exec -i) to avoid shell
 * quoting / newline issues that occur when embedding multi-line data inside
 * a sh -c "..." command string.
 */
function seedTestTrace() {
    const timestamp = new Date().toISOString();

    const docs = [
        {
            '@timestamp': timestamp,
            level: 200,
            level_name: 'INFO',
            message: 'E2E trace test — incoming request',
            channel: 'request',
            app_name: 'core',
            trace_id: TEST_TRACE_ID,
            request_id: 'e2e-req-001',
            request_uri: '/api/v1/test',
            request_method: 'POST',
            event_name: 'core.invoke.received',
            source_app: 'core',
            target_app: 'hello-agent',
            status: 'ok',
            duration_ms: 120,
            sequence_order: 1,
        },
        {
            '@timestamp': new Date(Date.now() + 50).toISOString(),
            level: 200,
            level_name: 'INFO',
            message: 'E2E trace test — agent response',
            channel: 'request',
            app_name: 'hello-agent',
            trace_id: TEST_TRACE_ID,
            request_id: 'e2e-req-002',
            request_uri: '/api/v1/a2a',
            request_method: 'POST',
            event_name: 'hello.a2a.response',
            source_app: 'hello-agent',
            target_app: 'core',
            status: 'ok',
            duration_ms: 80,
            sequence_order: 2,
        },
    ];

    // Build NDJSON bulk body
    const lines = [];
    for (const doc of docs) {
        lines.push(JSON.stringify({ index: { _index: INDEX_NAME } }));
        lines.push(JSON.stringify(doc));
    }
    const bulkBody = lines.join('\n') + '\n';

    // Ensure the index exists first
    execSync(
        `docker exec brama-opensearch-1 curl -s -X PUT '${OPENSEARCH_URL}/${INDEX_NAME}' -H 'Content-Type: application/json' -d '{}' 2>/dev/null || true`,
        { cwd: PROJECT_ROOT, timeout: 15000 },
    );

    // Pipe the NDJSON body via stdin to avoid shell quoting / newline issues
    execSync(
        `docker exec -i brama-opensearch-1 curl -s -X POST '${OPENSEARCH_URL}/_bulk' -H 'Content-Type: application/x-ndjson' --data-binary @-`,
        { cwd: PROJECT_ROOT, timeout: 15000, input: bulkBody },
    );

    // Refresh so documents are immediately searchable
    execSync(
        `docker exec brama-opensearch-1 curl -s -X POST '${OPENSEARCH_URL}/${INDEX_NAME}/_refresh'`,
        { cwd: PROJECT_ROOT, timeout: 15000 },
    );
}

/**
 * Remove test trace documents from OpenSearch.
 */
function cleanupTestTrace() {
    const deleteBody = JSON.stringify({
        query: { term: { trace_id: TEST_TRACE_ID } },
    });

    const cmd = `docker exec brama-opensearch-1 curl -s -X POST '${OPENSEARCH_URL}/platform_logs_*/_delete_by_query' -H 'Content-Type: application/json' -d '${deleteBody.replace(/'/g, "'\\''")}'`;

    try {
        execSync(cmd, { cwd: PROJECT_ROOT, timeout: 15000 });
    } catch (_) {
        // Cleanup is best-effort; ignore failures
    }
}

Feature('Admin: Log Trace');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'setup: seed test trace data',
    async () => {
        seedTestTrace();
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page shows sequence diagram container',
    async ({ I, logsPage, logTracePage }) => {
        await logsPage.open();
        logsPage.seeTraceLink();

        await logsPage.clickFirstTraceLink();
        I.seeElement('.sequence-diagram');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page shows view toggle buttons',
    async ({ I, logsPage, logTracePage }) => {
        await logsPage.open();
        logsPage.seeTraceLink();

        await logsPage.clickFirstTraceLink();
        I.seeElement('[data-trace-view-button="diagram"]');
        I.seeElement('[data-trace-view-button="classic"]');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page can switch to classic view',
    async ({ I, logsPage, logTracePage }) => {
        await logsPage.open();
        logsPage.seeTraceLink();

        await logsPage.clickFirstTraceLink();
        await logTracePage.switchToClassicView();
        I.seeElement('#trace-view-classic.active');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page shows waterfall section',
    async ({ I, logsPage, logTracePage }) => {
        await logsPage.open();
        logsPage.seeTraceLink();

        await logsPage.clickFirstTraceLink();
        I.seeElement('.trace-waterfall');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'cleanup: remove test trace data',
    async () => {
        cleanupTestTrace();
    },
).tag('@admin').tag('@logs').tag('@trace');
