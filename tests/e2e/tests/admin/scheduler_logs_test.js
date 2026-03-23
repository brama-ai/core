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

        I.seeElement('//tbody//tr[1]//a[contains(@href, "/logs")]');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'clicking logs link navigates to job logs page',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tbody//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card .admin-table', 10);

        I.seeInCurrentUrl('/logs');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'job logs page shows execution history table',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tbody//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card .admin-table', 10);

        // Table headers are rendered uppercase via CSS text-transform,
        // so check the raw th text with XPath normalize-space.
        I.seeElement('//th[contains(text(), "Початок") or contains(text(), "ПОЧАТОК")]');
        I.seeElement('//th[contains(text(), "Завершення") or contains(text(), "ЗАВЕРШЕННЯ")]');
        I.seeElement('//th[contains(text(), "Тривалість") or contains(text(), "ТРИВАЛІСТЬ")]');
        I.seeElement('//th[contains(text(), "Статус") or contains(text(), "СТАТУС")]');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'job logs page shows job information header',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tbody//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card', 10);

        I.see('Скіл');
        I.see('Cron');
        I.see('Всього записів');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'job logs page has back navigation link',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tbody//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card', 10);

        I.see('Планувальник');
        I.click('Планувальник');
        await I.waitForElement('table', 10);
        I.seeInCurrentUrl('/admin/scheduler');
        I.dontSeeInCurrentUrl('/logs');
    },
).tag('@admin').tag('@scheduler').tag('@logs');

Scenario(
    'job logs page shows pagination for many entries',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('//tbody//tr[1]//a[contains(@href, "/logs")]');
        await I.waitForElement('.glass-card', 10);

        // The total entries count is shown in a span outside .glass-card,
        // e.g. "Скіл: ... · Cron: ... · Всього записів: 5"
        const headerText = await I.grabTextFrom(
            '//span[contains(text(), "записів") or contains(text(), "entries")]',
        );
        const match = headerText.match(/(\d+)\s*$/);
        const total = match ? parseInt(match[1], 10) : 0;

        if (total > 50) {
            I.seeElement('//a[contains(@href, "page=2")]');
        } else {
            I.dontSee('Попередня');
            I.dontSee('Наступна');
        }
    },
).tag('@admin').tag('@scheduler').tag('@logs');