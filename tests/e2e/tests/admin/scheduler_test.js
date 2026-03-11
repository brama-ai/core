// E2E: Admin scheduler page
// Verifies the scheduler page is accessible, shows scheduled jobs,
// and action buttons (run, toggle) work.

const assert = require('assert');

Feature('Admin: Scheduler');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'scheduler page is accessible and shows title',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();
        I.see('Планувальник завдань');
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'scheduler sidebar link navigates to scheduler page',
    async ({ I }) => {
        I.amOnPage('/admin/agents');
        I.click('Планувальник');
        await I.waitForElement('table', 5);
        I.seeInCurrentUrl('/admin/scheduler');
        I.see('Планувальник завдань');
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'scheduler page shows hello-agent daily-greeting job',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();
        schedulerPage.seeJob('hello-agent', 'daily-greeting');
        I.see('hello.greet', 'table');
        I.see('0 9 * * *', 'table');
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'daily-greeting job is enabled',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();
        schedulerPage.seeJobEnabled('daily-greeting');
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'scheduler page shows Run and Toggle buttons for each job',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();
        schedulerPage.seeRunButton('daily-greeting');
        schedulerPage.seeToggleButton('daily-greeting');
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'toggling a job disables it and button text changes',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        // Click "Вимкнути" on daily-greeting
        I.click(locate('button').withText('Вимкнути').inside('//tr[contains(., "daily-greeting")]'));

        // Page reloads — wait for table
        await I.waitForElement('table', 10);

        // Now the button should say "Увімкнути" and badge should say "ні"
        I.see('Увімкнути', '//tr[contains(., "daily-greeting")]');
        I.seeElement('//tr[contains(., "daily-greeting")]//span[contains(@class, "badge-log-error") and contains(text(), "ні")]');

        // Toggle back to enabled
        I.click(locate('button').withText('Увімкнути').inside('//tr[contains(., "daily-greeting")]'));
        await I.waitForElement('table', 10);

        // Verify restored
        schedulerPage.seeJobEnabled('daily-greeting');
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'run now button triggers the job',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        // Click "Запустити" on daily-greeting
        I.click(locate('button').withText('Запустити').inside('//tr[contains(., "daily-greeting")]'));

        // Page reloads after triggering
        await I.waitForElement('table', 10);

        // Job should still be visible (it just set next_run_at = now)
        schedulerPage.seeJob('hello-agent', 'daily-greeting');
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'scheduler page shows job count',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        // The page shows "Всього завдань: N"
        I.see('Всього завдань:');
        const text = await I.grabTextFrom('//span[contains(text(), "Всього завдань")]');
        const count = parseInt(text.replace(/\D/g, ''), 10);
        assert.ok(count >= 1, `Expected at least 1 job, got ${count}`);
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'create job button opens modal form',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.see('Створити завдання');
        I.click('+ Створити завдання');
        await I.waitForVisible('#createJobForm', 5);

        I.seeElement('#cj_agent');
        I.seeElement('#cj_job_name');
        I.seeElement('#cj_skill');
        I.seeElement('#cj_cron');
        I.seeElement('#cj_payload');
        I.seeElement('#cj_timezone');
        I.seeElement('#cj_retries');
        I.seeElement('#cj_delay');
        I.see('Створити', '#createJobBtn');
        I.see('Скасувати');
    },
).tag('@admin').tag('@scheduler');

Scenario(
    'creating a job via modal adds it to the table',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('+ Створити завдання');
        await I.waitForVisible('#createJobForm', 5);

        I.selectOption('#cj_agent', 'hello-agent');
        I.fillField('#cj_job_name', 'e2e-test-job');
        I.fillField('#cj_skill', 'hello.greet');
        I.fillField('#cj_cron', '30 12 * * *');
        I.fillField('#cj_payload', '{"name": "E2E"}');
        I.fillField('#cj_timezone', 'Europe/Kyiv');

        I.click('#createJobBtn');

        // Page reloads after creation
        await I.waitForElement('table', 10);
        I.see('e2e-test-job', 'table');
        I.see('hello.greet', 'table');
        I.see('30 12 * * *', 'table');
    },
).tag('@admin').tag('@scheduler');
