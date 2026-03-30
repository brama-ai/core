// E2E: Admin scheduler page
// Verifies the scheduler page is accessible, shows scheduled jobs,
// and action buttons (run, toggle) work.

const assert = require('assert');
const { execSync } = require('child_process');

const PROJECT_ROOT = process.cwd().replace(/\/tests\/e2e$/, '');
const CORE_DB_NAME = process.env.CORE_DB_NAME || 'brama_test';
const POSTGRES_DSN = process.env.POSTGRES_DSN || `postgresql://app:app@localhost:5432/${CORE_DB_NAME}`;
const PSQL = process.env.PSQL_CMD || `psql ${POSTGRES_DSN} -c`;

Feature('Admin: Scheduler');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
    // Ensure daily-greeting job is enabled and reset retry count before each test
    try {
        execSync(
            `${PSQL} "UPDATE scheduled_jobs SET enabled = true, retry_count = 0, last_status = 'ok' WHERE job_name = 'daily-greeting'"`,
            { cwd: PROJECT_ROOT },
        );
    } catch (_) {
        // Ignore if job doesn't exist
    }
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

        // Wait for the AJAX request to complete and page to reload
        await I.wait(2);
        await I.waitForElement('table', 10);

        // Now the button should say "Увімкнути" and badge should say "ні"
        I.see('Увімкнути', '//tr[contains(., "daily-greeting")]');
        I.seeElement('//tr[contains(., "daily-greeting")]//span[contains(@class, "badge-log-error") and contains(text(), "ні")]');

        // Toggle back to enabled
        I.click(locate('button').withText('Увімкнути').inside('//tr[contains(., "daily-greeting")]'));
        await I.wait(2);
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
        await I.wait(1); // wait for skills dropdown to populate
        I.fillField('#cj_job_name', 'e2e-test-job');
        I.selectOption('#cj_skill', 'hello.greet');
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

Scenario(
    'visual cron builder sets hourly schedule and job is created',
    async ({ I, schedulerPage }) => {
        await schedulerPage.open();

        I.click('+ Створити завдання');
        await I.waitForVisible('#createJobForm', 5);

        // Fill agent and skill
        I.selectOption('#cj_agent', 'hello-agent');
        await I.wait(1);
        I.selectOption('#cj_skill', 'hello.greet');
        I.fillField('#cj_job_name', 'e2e-visual-cron-job');

        // Switch to visual cron builder
        I.click('#cronModeToggle');

        // Wait for Vue component to load from ESM CDN and mount
        await I.waitForElement('#cron-builder-app .cron-light', 15);

        // Use Playwright API directly to interact with custom dropdown controls
        await I.usePlaywrightTo('select hourly in cron builder', async ({ page }) => {
            // 1. Open period dropdown (first .cl-select button — shows "Year" by default)
            await page.locator('#cron-builder-app .cl-select').first().locator('.cl-btn').click();
            await page.waitForSelector('#cron-builder-app .cl-menu', { timeout: 3000 });

            // 2. Click "Hour" — second row in the period menu (Minute=0, Hour=1)
            await page.locator('#cron-builder-app .cl-menu .cl-row').nth(1).click();
            await page.waitForTimeout(500);

            // 3. Open minute dropdown (second .cl-select — shows "every")
            await page.locator('#cron-builder-app .cl-select').nth(1).locator('.cl-btn').click();
            await page.waitForSelector('#cron-builder-app .cl-menu', { timeout: 3000 });

            // 4. Click "00" — first item in first row, first column
            await page.locator('#cron-builder-app .cl-menu .cl-row').first()
                .locator('.cl-col').first().click();
            await page.waitForTimeout(300);

            // 5. Close dropdown by clicking the form title area
            await page.locator('#createJobForm h3, #createJobModal h3').first().click({ force: true }).catch(() => {});
            await page.click('#cj_job_name');
            await page.waitForTimeout(300);
        });

        // Verify the hidden cron input got a valid hourly expression
        const cronValue = await I.grabValueFrom('#cj_cron');
        assert.ok(
            cronValue.length >= 5 && cronValue.includes('*'),
            `Expected valid hourly cron expression, got "${cronValue}"`,
        );

        // Fill remaining fields
        I.fillField('#cj_payload', '{"source": "visual-cron"}');
        I.fillField('#cj_timezone', 'UTC');

        // Submit
        I.click('#createJobBtn');

        // Page reloads after creation
        await I.waitForElement('table', 10);

        // Verify job appears in the table
        I.see('e2e-visual-cron-job', 'table');
        I.see('hello.greet', 'table');
        I.see('hello-agent', 'table');
    },
).tag('@admin').tag('@scheduler').tag('@visual-cron');

// Skipped: Requires add-scheduler-delivery implementation
xScenario(
    'create job with delivery channel selected (requires add-scheduler-delivery)',
    async ({ I, schedulerPage }) => {
    },
).tag('@admin').tag('@scheduler').tag('@delivery');
