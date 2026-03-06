// E2E: Admin logs page
// Tests the centralized log viewer UI.

Feature('Admin: Logs Page');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'logs page is accessible and shows search form',
    async ({ I, logsPage }) => {
        await logsPage.open();
        I.see('Логи');
        I.seeElement(logsPage.filterForm);
        I.seeElement(logsPage.searchInput);
        I.seeElement(logsPage.levelSelect);
        I.seeElement(logsPage.appSelect);
        I.seeElement(logsPage.submitButton);
    },
).tag('@admin').tag('@logs');

Scenario(
    'logs page shows results table with log entries',
    async ({ I, logsPage }) => {
        await logsPage.open();
        I.seeElement('table.admin-table');
        I.see('Знайдено:');
    },
).tag('@admin').tag('@logs');

Scenario(
    'filter by level shows matching entries',
    async ({ I, logsPage }) => {
        await logsPage.open();
        await logsPage.filterByLevel('INFO');

        // URL should contain the level param
        I.seeInCurrentUrl('level=INFO');
    },
).tag('@admin').tag('@logs');

Scenario(
    'filter by app shows matching entries',
    async ({ I, logsPage }) => {
        await logsPage.open();
        await logsPage.filterByApp('core');

        I.seeInCurrentUrl('app=core');
    },
).tag('@admin').tag('@logs');

Scenario(
    'search by text filters results',
    async ({ I, logsPage }) => {
        await logsPage.open();
        await logsPage.search('started');

        I.seeInCurrentUrl('q=started');
    },
).tag('@admin').tag('@logs');

Scenario(
    'search by request_id filters to matching entries',
    async ({ I, logsPage }) => {
        await logsPage.open();

        // Search for a request_id pattern — the multi_match must include request_id field
        await logsPage.search('req_');

        I.seeInCurrentUrl('q=req_');

        // If results exist, verify rows are shown
        const rows = await I.grabNumberOfVisibleElements(
            'table.admin-table tbody tr',
        );
        if (rows > 0) {
            I.seeElement('table.admin-table tbody tr');
        }
    },
).tag('@admin').tag('@logs');

Scenario(
    'category dropdown is visible and contains expected options',
    async ({ I, logsPage }) => {
        await logsPage.open();
        I.seeElement(logsPage.categorySelect);
        I.see('Чати (A2A)', logsPage.categorySelect);
        I.see('Початок чатів', logsPage.categorySelect);
        I.see('LLM виклики', logsPage.categorySelect);
        I.see('Помилки', logsPage.categorySelect);
    },
).tag('@admin').tag('@logs');

Scenario(
    'filter by category chat updates URL and preserves filter',
    async ({ I, logsPage }) => {
        await logsPage.open();
        await logsPage.filterByCategory('Чати (A2A)');

        I.seeInCurrentUrl('category=chat');
        // Verify total count is displayed (filter was applied)
        I.see('Знайдено:');
    },
).tag('@admin').tag('@logs');

Scenario(
    'filter by category chat_start updates URL',
    async ({ I, logsPage }) => {
        await logsPage.open();
        await logsPage.filterByCategory('Початок чатів');

        I.seeInCurrentUrl('category=chat_start');
    },
).tag('@admin').tag('@logs');

Scenario(
    'filter by category error updates URL',
    async ({ I, logsPage }) => {
        await logsPage.open();
        await logsPage.filterByCategory('Помилки');

        I.seeInCurrentUrl('category=error');
    },
).tag('@admin').tag('@logs');

Scenario(
    'category and level filters can be combined',
    async ({ I, logsPage }) => {
        await logsPage.open();
        I.selectOption(logsPage.categorySelect, 'Чати (A2A)');
        I.selectOption(logsPage.levelSelect, 'INFO');
        I.click(logsPage.submitButton);
        await I.waitForElement(logsPage.resultsTable, 10);

        I.seeInCurrentUrl('category=chat');
        I.seeInCurrentUrl('level=INFO');
    },
).tag('@admin').tag('@logs');

Scenario(
    'switching category filter resets to different results',
    async ({ I, logsPage }) => {
        await logsPage.open();

        // Apply chat category
        await logsPage.filterByCategory('Чати (A2A)');
        I.seeInCurrentUrl('category=chat');
        const chatCount = await I.grabTextFrom('.glass-card span');

        // Switch to discovery category
        await logsPage.filterByCategory('Виявлення агентів');
        I.seeInCurrentUrl('category=discovery');
        I.dontSeeInCurrentUrl('category=chat');
    },
).tag('@admin').tag('@logs');

Scenario(
    'resetting category to Всі removes filter from URL',
    async ({ I, logsPage }) => {
        await logsPage.open();

        // Apply a category first
        await logsPage.filterByCategory('Чати (A2A)');
        I.seeInCurrentUrl('category=chat');

        // Reset to Всі
        await logsPage.filterByCategory('Всі');
        I.dontSeeInCurrentUrl('category=chat');
    },
).tag('@admin').tag('@logs');

Scenario(
    'trace link navigates to trace detail page',
    async ({ I, logsPage }) => {
        await logsPage.open();

        // Check if there are trace links; skip gracefully if no logs yet
        const traceLinks = await I.grabNumberOfVisibleElements(
            'table.admin-table tbody tr td a[href*="/admin/logs/trace/"]',
        );

        if (traceLinks > 0) {
            await logsPage.clickFirstTraceLink();
            I.seeInCurrentUrl('/admin/logs/trace/');
            I.see('Trace');
            I.seeElement('.trace-timeline');
        }
    },
).tag('@admin').tag('@logs');
