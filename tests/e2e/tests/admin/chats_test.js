// E2E: Admin chats page
// Tests the chats section that shows A2A message audit grouped by trace_id.
// Seeds a test record into a2a_message_audit, verifies it appears, and cleans up.

const { execSync } = require('child_process');
const assert = require('assert');

const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'brama_test';
const POSTGRES_DSN = process.env.POSTGRES_DSN || `postgresql://app:app@postgres:5432/${CORE_DB_NAME}`;
const PSQL = process.env.PSQL_CMD || `psql ${POSTGRES_DSN} -c`;
const TEST_TRACE_ID = 'e2e-test-trace-chats-001';
const TEST_AGENT = 'hello-agent';
const TEST_SKILL = 'hello.greet';

Feature('Admin: Chats');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'setup: seed a2a_message_audit with test record',
    async () => {
        // Clean up any leftover test data first
        execSync(
            `${PSQL} "DELETE FROM a2a_message_audit WHERE trace_id LIKE 'e2e-test-trace-%'"`,
            { cwd: PROJECT_ROOT },
        );

        // Insert a test audit record
        execSync(
            `${PSQL} "INSERT INTO a2a_message_audit (skill, agent, trace_id, request_id, duration_ms, status, actor, created_at) VALUES ('${TEST_SKILL}', '${TEST_AGENT}', '${TEST_TRACE_ID}', 'e2e-req-001', 150, 'completed', 'openclaw', now())"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@chats');

Scenario(
    'chats page is accessible and shows filter form',
    async ({ I, chatsPage }) => {
        await chatsPage.open();
        I.see('Чати');
        I.seeElement(chatsPage.filterForm);
        I.seeElement(chatsPage.agentInput);
        I.seeElement(chatsPage.statusInput);
        I.seeElement(chatsPage.submitButton);
    },
).tag('@admin').tag('@chats');

Scenario(
    'chats page shows seeded test record in table',
    async ({ I, chatsPage }) => {
        await chatsPage.open();
        I.seeElement(chatsPage.chatsTable);
        chatsPage.seeChatWithAgent(TEST_AGENT);
        I.see(TEST_SKILL, chatsPage.chatsTable);
        I.see('completed', chatsPage.chatsTable);
    },
).tag('@admin').tag('@chats');

Scenario(
    'filter by agent shows matching chats',
    async ({ I, chatsPage }) => {
        await chatsPage.open();
        await chatsPage.filterByAgent(TEST_AGENT);

        I.seeInCurrentUrl(`agent=${TEST_AGENT}`);
        chatsPage.seeChatWithAgent(TEST_AGENT);
    },
).tag('@admin').tag('@chats');

Scenario(
    'filter by status shows matching chats',
    async ({ I, chatsPage }) => {
        await chatsPage.open();
        await chatsPage.filterByStatus('completed');

        I.seeInCurrentUrl('status=completed');
        I.seeElement(`${chatsPage.tableBody} tr`);
    },
).tag('@admin').tag('@chats');

Scenario(
    'filter by non-existent agent shows empty state',
    async ({ I, chatsPage }) => {
        await chatsPage.open();
        await chatsPage.filterByAgent('non-existent-agent-xyz');

        I.seeInCurrentUrl('agent=non-existent-agent-xyz');
        I.seeElement(chatsPage.emptyState);
        I.see('Немає чатів');
    },
).tag('@admin').tag('@chats');

Scenario(
    'clicking chat row navigates to chat detail page',
    async ({ I, chatsPage }) => {
        await chatsPage.open();
        await chatsPage.clickFirstChat();

        I.seeInCurrentUrl('/admin/chats/');
        I.see('Деталі чату');
        I.see('Trace ID');
    },
).tag('@admin').tag('@chats');

Scenario(
    'chat detail page shows trace link button',
    async ({ I, chatsPage }) => {
        await chatsPage.open();
        await chatsPage.clickFirstChat();

        I.seeElement('a[href*="/admin/logs/trace/"]');
        I.see('Trace');
    },
).tag('@admin').tag('@chats');

Scenario(
    'trace link in chats table navigates to trace page',
    async ({ I, chatsPage }) => {
        await chatsPage.open();

        const traceLinks = await I.grabNumberOfVisibleElements(
            `${chatsPage.tableBody} tr td a.chat-trace-link`,
        );

        if (traceLinks > 0) {
            await chatsPage.clickFirstTraceLink();
            I.seeInCurrentUrl('/admin/logs/trace/');
        }
    },
).tag('@admin').tag('@chats');

Scenario(
    'cleanup: remove test audit records',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM a2a_message_audit WHERE trace_id LIKE 'e2e-test-trace-%'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@chats');
