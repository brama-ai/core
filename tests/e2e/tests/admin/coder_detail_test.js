// E2E: Admin coder task detail
// Verifies the coder task detail page shows logs, timeline, artifacts.
// UC: CUJ-16 — Coder task detail shows stage timeline, logs, artifacts.

const { execSync } = require('child_process');

const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'ai_community_platform_test';
const PSQL = `docker compose exec -T postgres psql -U app -d ${CORE_DB_NAME} -c`;

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
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail page shows task title and status',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.click('E2E Test Task for Detail');
        await I.waitForElement('.card', 5);

        I.see('E2E Test Task for Detail');
        I.seeElement('.badge');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail shows stage timeline',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.click('E2E Test Task for Detail');
        await I.waitForElement('.card', 5);

        I.see('Етапи виконання');
        I.see('planner');
        I.see('architect');
        I.see('coder');
        I.see('auditor');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail shows description',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.click('E2E Test Task for Detail');
        await I.waitForElement('.card', 5);

        I.see('Опис');
        I.see('Test description');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail shows artifacts section',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.click('E2E Test Task for Detail');
        await I.waitForElement('.card', 5);

        I.see('Артефакти');
        I.see('Summary:');
        I.see('Artifacts:');
        I.see('Builder file:');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder task detail shows logs panel',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.click('E2E Test Task for Detail');
        await I.waitForElement('.card', 5);

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