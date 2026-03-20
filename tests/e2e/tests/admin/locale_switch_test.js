// E2E: Admin locale switcher
// Verifies locale switching in the admin panel header.
// UC: CUJ-07 — Locale switch language UI translates.

Feature('Admin: Locale Switcher');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'locale switcher is visible in header',
    async ({ I }) => {
        I.amOnPage('/admin/dashboard');
        I.seeElement('#localeSwitcher button');
    },
).tag('@admin').tag('@locale');

Scenario(
    'switching from Ukrainian to English updates UI',
    async ({ I, localePage }) => {
        await localePage.open();
        localePage.seeSwitcher();

        await localePage.switchToLocale('en');

        I.see('Dashboard');
        I.see('Agents');
        I.see('Settings');
    },
).tag('@admin').tag('@locale');

Scenario(
    'locale persists across page navigation',
    async ({ I, localePage }) => {
        await localePage.open();
        await localePage.switchToLocale('en');

        I.see('Dashboard');
        I.amOnPage('/admin/agents');
        I.see('Agents');
        I.see('Settings');

        I.amOnPage('/admin/scheduler');
        I.see('Scheduler');
    },
).tag('@admin').tag('@locale');

Scenario(
    'switching back to Ukrainian restores Ukrainian labels',
    async ({ I, localePage }) => {
        await localePage.open();

        await localePage.switchToLocale('en');
        I.see('Dashboard');

        await localePage.switchToLocale('uk');
        I.see('Головна');
    },
).tag('@admin').tag('@locale');