// E2E: Admin scheduler job logs
// Verifies the scheduler job logs page shows execution history.
// UC: CUJ-21 — Scheduler job logs page shows execution history.

Feature('Admin: Scheduler Job Logs');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'scheduler page has logs link for each job',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();
        I.see('Планувальник завдань');

        I.seeElement('//tr[1]//a[contains(@href, "/logs")]');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'clicking logs link navigates to job logs page',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card table', 10);

        I.seeInCurrentUrl('/logs');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'job logs page shows execution history table',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card table', 10);

        I.see('Старт');
        I.see('Завершення');
        I.see('Тривалість');
        I.see('Статус');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'job logs page shows job information header',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card', 10);

        I.see('skill:');
        I.see('cron:');
        I.see('Всього:');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'job logs page has back navigation link',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card', 10);

        I.see('Назад');
        I.click('Назад');
        await I.waitForElement('table', 10);
        I.seeInCurrentUrl('/admin/scheduler');
        I.dontSeeInCurrentUrl('/logs');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'job logs page shows pagination for many entries',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card', 10);

        const totalText = await I.grabTextFrom('.glass-card span');
        const total = parseInt(totalText.replace(/\D/g, ''), 10);

        if (total > 50) {
            I.seeElement('//a[contains(@href, "page=2")]');
        } else {
            I.dontSee('Попередня');
            I.dontSee('Наступна');
        }
    },
).tag('@admin').tag('@scheduler').tag('@logs');