// E2E: Admin tenant management
// Verifies CRUD operations for tenants: list, create, edit, delete.
// Requires ROLE_SUPER_ADMIN (default admin user).

Feature('Admin: Tenant Management');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'tenants page is accessible and shows title',
    async ({ I, tenantsPage }) => {
        await tenantsPage.open();
        I.see('Управління тенантами');
    },
).tag('@admin').tag('@tenant');

Scenario(
    'tenants sidebar link navigates to tenants page',
    async ({ I }) => {
        I.amOnPage('/admin/dashboard');
        I.click('Тенанти');
        await I.waitForElement('table', 5);
        I.seeInCurrentUrl('/admin/tenants');
        I.see('Управління тенантами');
    },
).tag('@admin').tag('@tenant');

Scenario(
    'default tenant is visible in the list',
    async ({ I, tenantsPage }) => {
        await tenantsPage.open();
        tenantsPage.seeTenant('Default');
        tenantsPage.seeTenantEnabled('Default');
    },
).tag('@admin').tag('@tenant');

Scenario(
    'create tenant form is accessible',
    async ({ I, tenantsPage }) => {
        await tenantsPage.open();
        await tenantsPage.clickCreate();
        I.seeInCurrentUrl('/admin/tenants/create');
        I.see('Створити тенант');
        I.seeElement('input[name="name"]');
    },
).tag('@admin').tag('@tenant');

Scenario(
    'creating a tenant with empty name shows error',
    async ({ I, tenantsPage }) => {
        I.amOnPage('/admin/tenants/create');
        await I.waitForElement('input[name="name"]', 5);

        // Remove HTML5 "required" attribute so the form submits to the server
        I.executeScript(() => {
            document.querySelector('input[name="name"]').removeAttribute('required');
        });

        I.click('Створити');
        await I.waitForElement('form', 5);
        I.see('Назва тенанта');
    },
).tag('@admin').tag('@tenant');

Scenario(
    'creating a tenant adds it to the list',
    async ({ I, tenantsPage }) => {
        const name = 'E2E Tenant ' + Date.now();
        await tenantsPage.open();
        await tenantsPage.createTenant(name);

        // Should redirect to list
        I.seeInCurrentUrl('/admin/tenants');
        tenantsPage.seeTenant(name);
    },
).tag('@admin').tag('@tenant');

Scenario(
    'editing a tenant updates its name',
    async ({ I, tenantsPage }) => {
        // Create a tenant first
        const originalName = 'Edit Test ' + Date.now();
        await tenantsPage.open();
        await tenantsPage.createTenant(originalName);

        // Edit it
        await tenantsPage.editTenant(originalName);
        I.seeInCurrentUrl('/edit');

        const newName = 'Renamed ' + Date.now();
        I.fillField('name', newName);
        I.click('Зберегти');

        await I.waitForElement('table', 10);
        I.seeInCurrentUrl('/admin/tenants');
        tenantsPage.seeTenant(newName);
    },
).tag('@admin').tag('@tenant');

Scenario(
    'deleting an empty tenant removes it from the list',
    async ({ I, tenantsPage }) => {
        // Create a tenant to delete
        const name = 'Delete Me ' + Date.now();
        await tenantsPage.open();
        await tenantsPage.createTenant(name);
        tenantsPage.seeTenant(name);

        // Delete it — register dialog handler, then trigger the confirm dialog
        await I.executeScript((tenantName) => {
            // Override confirm to auto-accept, avoiding timing issues with acceptPopup
            window.__origConfirm = window.confirm;
            window.confirm = () => true;
        }, name);
        await tenantsPage.deleteTenant(name);
        await I.waitForElement('table', 10);

        I.dontSee(name, 'table');
    },
).tag('@admin').tag('@tenant');

Scenario(
    'tenants page shows total count',
    async ({ I, tenantsPage }) => {
        await tenantsPage.open();
        I.see('Усього:');
    },
).tag('@admin').tag('@tenant');
