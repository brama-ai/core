// E2E: Admin coder task detail
// Verifies the coder task detail page shows logs, timeline, artifacts.
// UC: CUJ-16 — Coder task detail shows stage timeline, logs, artifacts.

const { execSync } = require('child_process');

const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'brama_test';
const POSTGRES_DSN = process.env.POSTGRES_DSN || `postgresql://app:app@postgres:5432/${CORE_DB_NAME}`;
const PSQL = process.env.PSQL_CMD || `psql ${POSTGRES_DSN} -c`;

let taskId = null;

Feature('Admin: Coder Task Detail');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'setup: create a test task for detail view',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM coder_task_logs WHERE task_id IN (SELECT id FROM coder_tasks WHERE title LIKE 'E2E Test%')"` +
            ` && ${PSQL} "DELETE FROM coder_tasks WHERE title LIKE 'E2E Test%'"`,
            { cwd: PROJECT_ROOT },
        );
        execSync(
            `${PSQL} "INSERT INTO coder_tasks (id, title, description, status, priority, created_at, updated_at) VALUES (gen_random_uuid(), 'E2E Test Task for Detail', 'Test description', 'queued', 1, now(), now())"`,
            { cwd: PROJECT_ROOT },
        );
        // Get the task ID for direct navigation
        const result = execSync(
            `${PSQL} "SELECT id FROM coder_tasks WHERE title = 'E2E Test Task for Detail' LIMIT 1" -t -A`,
            { cwd: PROJECT_ROOT, encoding: 'utf-8' },
        ).trim();
        taskId = result;
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail page shows task title and status',
    async ({ I }) => {
        if (!taskId) {
            I.say('SKIP: task ID not available');
            return;
        }
        I.amOnPage(`/admin/coder/${taskId}`);
        await I.waitForText('E2E Test Task for Detail', 15);

        I.see('E2E Test Task for Detail');
        I.seeElement('.badge');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail shows stage timeline',
    async ({ I }) => {
        if (!taskId) {
            I.say('SKIP: task ID not available');
            return;
        }
        I.amOnPage(`/admin/coder/${taskId}`);
        await I.waitForText('Stage timeline', 15);

        I.see('Stage timeline');
        I.see('planner');
        I.see('architect');
        I.see('coder');
        I.see('auditor');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail shows description',
    async ({ I }) => {
        if (!taskId) {
            I.say('SKIP: task ID not available');
            return;
        }
        I.amOnPage(`/admin/coder/${taskId}`);
        await I.waitForText('Опис', 15);

        I.see('Опис');
        I.see('Test description');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail shows artifacts section',
    async ({ I }) => {
        if (!taskId) {
            I.say('SKIP: task ID not available');
            return;
        }
        I.amOnPage(`/admin/coder/${taskId}`);
        await I.waitForText('Артефакти', 15);

        I.see('Артефакти');
        I.see('Summary:');
        I.see('Artifacts:');
        I.see('Builder file:');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail shows logs panel',
    async ({ I }) => {
        if (!taskId) {
            I.say('SKIP: task ID not available');
            return;
        }
        I.amOnPage(`/admin/coder/${taskId}`);
        await I.waitForText('Логи', 15);

        I.see('Логи');
        I.seeElement('#log-panel');
    },
).tag('@admin').tag('@coder');

Scenario(
    'cleanup: remove test coder task',
    async () => {
        execSync(
            `${PSQL} "DELETE FROM coder_task_logs WHERE task_id IN (SELECT id FROM coder_tasks WHERE title LIKE 'E2E Test%')"` +
            ` && ${PSQL} "DELETE FROM coder_tasks WHERE title LIKE 'E2E Test%'"`,
            { cwd: PROJECT_ROOT },
        );
    },
).tag('@admin').tag('@coder');
