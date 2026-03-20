// E2E: Admin log trace visualization
// Verifies the log trace page shows sequence diagram and waterfall view.
// UC: CUJ-19 — Log trace shows sequence diagram, spans, classic view.

const { execSync } = require('child_process');

const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'ai_community_platform_test';
const PSQL = `docker compose exec -T postgres psql -U app -d ${CORE_DB_NAME} -c`;
const TEST_TRACE_ID = 'e2e-test-trace-001';

Feature('Admin: Log Trace');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'setup: seed test trace data',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM logs WHERE trace_id = '${TEST_TRACE_ID}'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page shows sequence diagram container',
    async ({ I, logsPage, logTracePage }) => {
        await logsPage.open();
        logsPage.seeLogEntry();

        await logTracePage.switchToDiagramView();
        I.seeElement('.sequence-diagram');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page shows view toggle buttons',
    async ({ I, logsPage, logTracePage }) => {
        await logsPage.open();

        I.seeElement('[data-trace-view-button="diagram"]');
        I.seeElement('[data-trace-view-button="classic"]');
    },
).tag('@admin').tag('@logs').tag('@trace');

Scenario(
    'log trace page can switch to classic view',
    async ({ I, logsPage, logTracePage }) => {
        await logsPage.open();

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
        execSync(
            `${PSQL} "DELETE FROM logs WHERE trace_id = '${TEST_TRACE_ID}'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@logs').tag('@trace');