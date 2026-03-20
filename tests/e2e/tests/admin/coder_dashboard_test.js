// E2E: Admin coder dashboard
// Verifies the coder dashboard shows task list, stats, and workers panel.
// UC: CUJ-15 — Coder dashboard shows stats, task list, workers.

Feature('Admin: Coder Dashboard');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'coder dashboard page is accessible and shows title',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.see('Coder');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder dashboard shows statistics grid',
    async ({ I, coderPage }) => {
        await coderPage.open();
        coderPage.seeStats();

        I.see('Завдання');
        I.see('У черзі');
        I.see('В роботі');
        I.see('Готово');
        I.see('Помилки');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder dashboard shows task list table',
    async ({ I, coderPage }) => {
        await coderPage.open();
        coderPage.seeTaskList();

        I.see('Назва');
        I.see('Статус');
        I.see('Пріоритет');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder dashboard shows workers panel',
    async ({ I, coderPage }) => {
        await coderPage.open();
        coderPage.seeWorkers();
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder dashboard shows recent activity feed',
    async ({ I, coderPage }) => {
        await coderPage.open();
        coderPage.seeRecentActivity();
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder dashboard has create task button',
    async ({ I, coderPage }) => {
        await coderPage.open();
        I.see('Створити завдання');
        I.seeElement('a[href*="/admin/coder/create"]');
    },
).tag('@admin').tag('@coder');

Scenario(
    'coder dashboard sidebar link navigates correctly',
    async ({ I }) => {
        I.amOnPage('/admin/agents');
        I.click('Coder');
        await I.waitForElement('.stats-grid', 5);
        I.seeInCurrentUrl('/admin/coder');
    },
).tag('@admin').tag('@coder');