// E2E: Admin coder events SSE
// Verifies the coder task detail page establishes SSE connection for real-time events.
// UC: CUJ-17 — Coder events SSE connection established and events rendered.

const { execSync } = require('child_process');

const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'ai_community_platform_test';
const PSQL = `docker compose exec -T postgres psql -U app -d ${CORE_DB_NAME} -c`;

Feature('Admin: Coder Events SSE');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'setup: create a test task for SSE testing',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM coder_task_logs WHERE task_id IN (SELECT id FROM coder_tasks WHERE title LIKE 'E2E SSE%')"` +
            ` && ${PSQL} "DELETE FROM coder_tasks WHERE title LIKE 'E2E SSE%'"`,
            { cwd: PROJECT_ROOT },
        );
        execSync(
            `${PSQL} "INSERT INTO coder_tasks (id, title, description, status, priority, created_at, updated_at) VALUES (gen_random_uuid(), 'E2E SSE Test Task', 'Test for SSE', 'in_progress', 1, now(), now())"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@coder').tag('@sse');

Scenario(
    'coder detail page includes SSE EventSource script',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.click('E2E SSE Test Task');
        await I.waitForElement('.card', 5);

        I.seeInSource('EventSource');
        I.seeInSource('/admin/coder/events');
    },
).tag('@admin').tag('@coder').tag('@sse');

Scenario(
    'coder detail page has log panel for real-time updates',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.click('E2E SSE Test Task');
        await I.waitForElement('.card', 5);

        I.seeElement('#log-panel');
    },
).tag('@admin').tag('@coder').tag('@sse');

Scenario(
    'cleanup: remove SSE test task',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM coder_task_logs WHERE task_id IN (SELECT id FROM coder_tasks WHERE title LIKE 'E2E SSE%')"` +
            ` && ${PSQL} "DELETE FROM coder_tasks WHERE title LIKE 'E2E SSE%'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@coder').tag('@sse');