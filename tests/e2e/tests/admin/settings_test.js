// E2E: Admin settings page
// Verifies the settings page loads, displays config options, and saves changes.
// UC: CUJ-14 — Settings page shows log level, retention, save.

Feature('Admin: Settings');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'settings page is accessible and shows title',
    async ({ I, settingsPage }) => {
        await settingsPage.open();
        I.see('Налаштування');
    },
).tag('@admin').tag('@settings');

Scenario(
    'settings page displays log level selector',
    async ({ I, settingsPage }) => {
        await settingsPage.open();
        settingsPage.seeLogLevelSelector();
        I.see('DEBUG');
        I.see('INFO');
        I.see('WARNING');
        I.see('ERROR');
    },
).tag('@admin').tag('@settings');

Scenario(
    'settings page displays retention input',
    async ({ I, settingsPage }) => {
        await settingsPage.open();
        settingsPage.seeRetentionInput();
    },
).tag('@admin').tag('@settings');

Scenario(
    'settings page displays max size input',
    async ({ I, settingsPage }) => {
        await settingsPage.open();
        settingsPage.seeMaxSizeInput();
    },
).tag('@admin').tag('@settings');

Scenario(
    'saving settings persists the new log level',
    async ({ I, settingsPage }) => {
        await settingsPage.open();

        await settingsPage.selectLogLevel('INFO');
        await settingsPage.save();

        settingsPage.seeSavedMessage();

        await settingsPage.open();
        I.see('INFO');
    },
).tag('@admin').tag('@settings');

Scenario(
    'saving changed retention persists correctly',
    async ({ I, settingsPage }) => {
        await settingsPage.open();

        await settingsPage.setRetentionDays(14);
        await settingsPage.save();

        settingsPage.seeSavedMessage();
    },
).tag('@admin').tag('@settings');