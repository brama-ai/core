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
// Dedicated trace ID for A2A sequence diagram tests (task 5.3 / CUJ-19)
const A2A_TRACE_ID = 'e2e-a2a-trace-seq-001';

// Build the index name for today (matches LogIndexManager::todayIndexName)
const today = new Date();
const INDEX_NAME = `platform_logs_${today.getFullYear()}_${String(today.getMonth() + 1).padStart(2, '0')}_${String(today.getDate()).padStart(2, '0')}`;

/**
 * Check if OpenSearch is accessible.
 */
function isOpenSearchAvailable() {
    try {
        const result = require('child_process').execSync(
            `curl -s --connect-timeout 3 --max-time 5 -o /dev/null -w "%{http_code}" '${OPENSEARCH_URL}/'`,
            { encoding: 'utf-8', timeout: 8000 },
        ).trim();
        return result !== '000' && result !== '';
    } catch (_) {
        return false;
    }
}

let opensearchAvailable = null;

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
            step: 'invoke_receive',
            source_app: 'core',
            target_app: 'hello-agent',
            status: 'started',
            duration_ms: 0,
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
            step: 'a2a_inbound',
            source_app: 'hello-agent',
            target_app: 'core',
            status: 'completed',
            duration_ms: 80,
            sequence_order: 2,
        },
    ];

    bulkInsert(docs);
}

/**
 * Seed a full A2A call trace with structured step events for sequence diagram tests.
 * Uses step values recognised by TraceSequenceProjector::isCallStep():
 *   a2a_outbound, a2a_inbound, llm_call, agent_card_fetch
 */
function seedA2ATrace() {
    const t0 = Date.now();

    const docs = [
        // Step 1: core receives invoke request
        {
            '@timestamp': new Date(t0).toISOString(),
            level: 200,
            level_name: 'INFO',
            message: 'A2A trace — invoke received',
            channel: 'app',
            app_name: 'core',
            trace_id: A2A_TRACE_ID,
            request_id: 'a2a-req-001',
            event_name: 'core.invoke.received',
            step: 'invoke_receive',
            source_app: 'core',
            target_app: 'openclaw',
            tool: 'hello.greet',
            status: 'started',
            duration_ms: 0,
            sequence_order: 1,
        },
        // Step 2: core resolves tool to hello-agent
        {
            '@timestamp': new Date(t0 + 5).toISOString(),
            level: 200,
            level_name: 'INFO',
            message: 'A2A trace — tool resolved',
            channel: 'app',
            app_name: 'core',
            trace_id: A2A_TRACE_ID,
            request_id: 'a2a-req-001',
            event_name: 'core.invoke.tool_resolved',
            step: 'tool_resolve',
            source_app: 'core',
            target_app: 'hello-agent',
            tool: 'hello.greet',
            status: 'completed',
            duration_ms: 5,
            sequence_order: 2,
        },
        // Step 3: core sends outbound A2A call to hello-agent (call step — appears in diagram)
        {
            '@timestamp': new Date(t0 + 10).toISOString(),
            level: 200,
            level_name: 'INFO',
            message: 'A2A trace — outbound A2A started',
            channel: 'app',
            app_name: 'core',
            trace_id: A2A_TRACE_ID,
            request_id: 'a2a-req-001',
            event_name: 'core.a2a.outbound.started',
            step: 'a2a_outbound',
            source_app: 'core',
            target_app: 'hello-agent',
            tool: 'hello.greet',
            intent: 'hello.greet',
            status: 'started',
            duration_ms: 0,
            sequence_order: 3,
            context: {
                step_input: { intent: 'hello.greet', payload: { name: 'E2E' } },
                capture_meta: { is_truncated: false, original_size_bytes: 42, captured_size_bytes: 42, redacted_fields_count: 0, truncated_values_count: 0 },
            },
        },
        // Step 4: hello-agent receives inbound A2A call (call step — appears in diagram)
        {
            '@timestamp': new Date(t0 + 20).toISOString(),
            level: 200,
            level_name: 'INFO',
            message: 'A2A trace — inbound A2A received',
            channel: 'app',
            app_name: 'hello-agent',
            trace_id: A2A_TRACE_ID,
            request_id: 'a2a-req-002',
            event_name: 'hello.a2a.received',
            step: 'a2a_inbound',
            source_app: 'hello-agent',
            target_app: 'core',
            tool: 'hello.greet',
            intent: 'hello.greet',
            status: 'started',
            duration_ms: 0,
            sequence_order: 4,
            context: {
                step_input: { intent: 'hello.greet', payload: { name: 'E2E' } },
                capture_meta: { is_truncated: false, original_size_bytes: 42, captured_size_bytes: 42, redacted_fields_count: 0, truncated_values_count: 0 },
            },
        },
        // Step 5: hello-agent completes and core receives response
        {
            '@timestamp': new Date(t0 + 100).toISOString(),
            level: 200,
            level_name: 'INFO',
            message: 'A2A trace — outbound A2A completed',
            channel: 'app',
            app_name: 'core',
            trace_id: A2A_TRACE_ID,
            request_id: 'a2a-req-001',
            event_name: 'core.a2a.outbound.completed',
            step: 'a2a_outbound',
            source_app: 'core',
            target_app: 'hello-agent',
            tool: 'hello.greet',
            intent: 'hello.greet',
            status: 'completed',
            duration_ms: 90,
            http_status_code: 200,
            sequence_order: 5,
            context: {
                step_output: { status: 'completed', output: 'Hello, E2E!' },
                capture_meta: { is_truncated: false, original_size_bytes: 38, captured_size_bytes: 38, redacted_fields_count: 0, truncated_values_count: 0 },
            },
        },
    ];

    bulkInsert(docs);
}

/**
 * Insert documents into OpenSearch via bulk API.
 * @param {Array<Object>} docs
 */
function bulkInsert(docs) {
    const lines = [];
    for (const doc of docs) {
        lines.push(JSON.stringify({ index: { _index: INDEX_NAME } }));
        lines.push(JSON.stringify(doc));
    }
    const bulkBody = lines.join('\n') + '\n';

    // Ensure the index exists first
    execSync(
        `curl -s -X PUT '${OPENSEARCH_URL}/${INDEX_NAME}' -H 'Content-Type: application/json' -d '{}' 2>/dev/null || true`,
        { cwd: PROJECT_ROOT, timeout: 15000 },
    );

    // Pipe the NDJSON body via stdin to avoid shell quoting / newline issues
    execSync(
        `curl -s -X POST '${OPENSEARCH_URL}/_bulk' -H 'Content-Type: application/x-ndjson' --data-binary @-`,
        { cwd: PROJECT_ROOT, timeout: 15000, input: bulkBody },
    );

    // Refresh so documents are immediately searchable
    execSync(
        `curl -s -X POST '${OPENSEARCH_URL}/${INDEX_NAME}/_refresh'`,
        { cwd: PROJECT_ROOT, timeout: 15000 },
    );
}

/**
 * Remove test trace documents from OpenSearch.
 */
function cleanupTestTrace() {
    const traceIds = [TEST_TRACE_ID, A2A_TRACE_ID];
    for (const traceId of traceIds) {
        const deleteBody = JSON.stringify({
            query: { term: { trace_id: traceId } },
        });

        const cmd = `curl -s -X POST '${OPENSEARCH_URL}/platform_logs_*/_delete_by_query' -H 'Content-Type: application/json' -d '${deleteBody.replace(/'/g, "'\\''")}'`;

        try {
            execSync(cmd, { cwd: PROJECT_ROOT, timeout: 15000 });
        } catch (_) {
            // Cleanup is best-effort; ignore failures
        }
    }
}

Feature('Admin: Log Trace');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'setup: seed test trace data',
    async ({ I }) => {
        opensearchAvailable = isOpenSearchAvailable();
        if (!opensearchAvailable) {
            I.say('SKIP: OpenSearch not available — skipping log trace tests');
            return;
        }
        seedTestTrace();
        seedA2ATrace();
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page shows sequence diagram container',
    async ({ I, logsPage, logTracePage }) => {
        if (!opensearchAvailable) {
            I.say('SKIP: OpenSearch not available');
            return;
        }
        await logsPage.open();
        logsPage.seeTraceLink();

        await logsPage.clickFirstTraceLink();
        I.seeElement('.sequence-diagram');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page shows view toggle buttons',
    async ({ I, logsPage, logTracePage }) => {
        if (!opensearchAvailable) {
            I.say('SKIP: OpenSearch not available');
            return;
        }
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
        if (!opensearchAvailable) {
            I.say('SKIP: OpenSearch not available');
            return;
        }
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
        if (!opensearchAvailable) {
            I.say('SKIP: OpenSearch not available');
            return;
        }
        await logsPage.open();
        logsPage.seeTraceLink();

        await logsPage.clickFirstTraceLink();
        I.seeElement('.trace-waterfall');
    },
).tag('@admin').tag('@logs').tag('@trace');

// ── New scenarios: A2A trace sequence diagram (task 5.3 / CUJ-19) ──────────

Scenario(
    'admin logs trace view page loads for a seeded A2A trace',
    async ({ I }) => {
        if (!opensearchAvailable) {
            I.say('SKIP: OpenSearch not available');
            return;
        }
        // Navigate directly to the seeded A2A trace page
        I.amOnPage(`/admin/logs/trace/${A2A_TRACE_ID}`);
        await I.waitForElement('.trace-sequence', 10);

        // Page title contains the trace ID
        I.seeInCurrentUrl(`/admin/logs/trace/${A2A_TRACE_ID}`);
        // Sequence container and waterfall are present
        I.seeElement('.sequence-diagram');
        I.seeElement('.trace-waterfall');
        I.seeElement('.trace-timeline');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'trace sequence diagram renders participants and arrows for a traced A2A call',
    async ({ I }) => {
        if (!opensearchAvailable) {
            I.say('SKIP: OpenSearch not available');
            return;
        }
        I.amOnPage(`/admin/logs/trace/${A2A_TRACE_ID}`);
        await I.waitForElement('.sequence-diagram', 10);

        // Participants lane is rendered
        I.seeElement('.sequence-participants');
        I.seeElement('.sequence-participant');

        // At least one sequence event row is rendered (a2a_outbound or a2a_inbound steps)
        I.seeElement('.sequence-event');

        // Arrows are rendered for the call steps
        I.seeElement('.sequence-arrow');

        // Participant labels include core and hello-agent
        I.see('core', '.sequence-participant');
        I.see('hello-agent', '.sequence-participant');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'step detail drill-down opens detail panel with sanitized input and output',
    async ({ I }) => {
        if (!opensearchAvailable) {
            I.say('SKIP: OpenSearch not available');
            return;
        }
        I.amOnPage(`/admin/logs/trace/${A2A_TRACE_ID}`);
        await I.waitForElement('.sequence-diagram', 10);

        // Ensure diagram view is active (default)
        I.seeElement('#trace-view-diagram.active');

        // The detail icon button must be present for at least one sequence event
        I.seeElement('.sequence-detail-icon');

        // Click the first detail icon to open the drill-down panel
        I.click('.sequence-detail-icon');

        // The detail panel should become active/visible
        await I.waitForElement('.sequence-detail-panel.active', 5);

        // Panel contains event metadata sections (Event, Input, Output, Headers / Meta)
        I.see('Event', '.sequence-detail-panel.active');
        I.see('Input', '.sequence-detail-panel.active');
        I.see('Output', '.sequence-detail-panel.active');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'cleanup: remove test trace data',
    async ({ I }) => {
        if (!opensearchAvailable) {
            return;
        }
        cleanupTestTrace();
    },
).tag('@admin').tag('@logs').tag('@trace');
