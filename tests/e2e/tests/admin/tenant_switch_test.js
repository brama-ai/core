// E2E: Admin tenant switcher
// Verifies tenant context switching in the admin panel header.
// The default admin user belongs to the "Default" tenant.

Feature('Admin: Tenant Switcher');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'dashboard shows current tenant name',
    async ({ I }) => {
        I.amOnPage('/admin/dashboard');
        I.see('Default');
    },
).tag('@admin').tag('@tenant');

Scenario(
    'tenant context persists across pages',
    async ({ I }) => {
        I.amOnPage('/admin/dashboard');
        I.see('Default');

        I.amOnPage('/admin/agents');
        I.see('Default');

        I.amOnPage('/admin/scheduler');
        I.see('Default');
    },
).tag('@admin').tag('@tenant');

Scenario(
    'creating a second tenant shows the switcher',
    async ({ I, tenantsPage }) => {
        // Create a second tenant so switcher appears
        const name = 'Switch Test ' + Date.now();
        await tenantsPage.open();
        await tenantsPage.createTenant(name);

        // Navigate to dashboard — switcher should now be visible
        I.amOnPage('/admin/dashboard');
        tenantsPage.seeSwitcher();
    },
).tag('@admin').tag('@tenant');

Scenario(
    'switching tenant changes context on dashboard',
    async ({ I, tenantsPage }) => {
        // Create a second tenant
        const name = 'Context Switch ' + Date.now();
        await tenantsPage.open();
        await tenantsPage.createTenant(name);

        // Go to dashboard and switch
        I.amOnPage('/admin/dashboard');
        await tenantsPage.switchToTenant(name);

        // Verify switcher shows new tenant
        tenantsPage.seeCurrentTenant(name);

        // Switch back to Default
        await tenantsPage.switchToTenant('Default');
        tenantsPage.seeCurrentTenant('Default');
    },
).tag('@admin').tag('@tenant');
