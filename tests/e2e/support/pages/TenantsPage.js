const { I } = inject();

module.exports = {
    url: '/admin/tenants',

    createButton: '//a[contains(text(), "Створити тенант")]',
    tenantsTable: 'table',

    /**
     * Navigate to the tenants management page.
     */
    async open() {
        I.amOnPage(this.url);
        await I.waitForElement(this.tenantsTable, 10);
    },

    /**
     * Assert a tenant row exists by name.
     */
    seeTenant(name) {
        I.see(name, this.tenantsTable);
    },

    /**
     * Assert a tenant has enabled status badge.
     */
    seeTenantEnabled(name) {
        I.seeElement(`//tr[contains(., "${name}")]//span[contains(text(), "Активний")]`);
    },

    /**
     * Assert a tenant has disabled status badge.
     */
    seeTenantDisabled(name) {
        I.seeElement(`//tr[contains(., "${name}")]//span[contains(text(), "Вимкнено")]`);
    },

    /**
     * Click the edit link for a tenant.
     */
    async editTenant(name) {
        I.click(locate('a').withText('Редагувати').inside(`//tr[contains(., "${name}")]`));
        await I.waitForElement('form', 5);
    },

    /**
     * Click the delete button for a tenant (requires confirmation).
     */
    async deleteTenant(name) {
        I.click(locate('button').withText('Видалити').inside(`//tr[contains(., "${name}")]`));
    },

    /**
     * Click the create tenant button.
     */
    async clickCreate() {
        I.click(this.createButton);
        await I.waitForElement('form', 5);
    },

    /**
     * Fill and submit the create tenant form.
     */
    async createTenant(name) {
        await this.clickCreate();
        I.fillField('name', name);
        I.click('Створити');
        await I.waitForElement(this.tenantsTable, 10);
    },

    // ---- Tenant Switcher (in layout header) ----

    switcherButton: '#tenantSwitcher button',
    switcherDropdown: '#tenantDropdown',

    /**
     * Check if the tenant switcher is visible in the header.
     */
    seeSwitcher() {
        I.seeElement(this.switcherButton);
    },

    /**
     * Check if the switcher is hidden (single tenant user).
     */
    dontSeeSwitcher() {
        I.dontSeeElement(this.switcherButton);
    },

    /**
     * Open the tenant switcher dropdown.
     */
    async openSwitcher() {
        I.click(this.switcherButton);
        await I.waitForElement(`${this.switcherDropdown}.show`, 3);
    },

    /**
     * Select a tenant from the switcher dropdown.
     */
    async switchToTenant(name) {
        await this.openSwitcher();
        I.click(locate('button').withText(name).inside(this.switcherDropdown));
        await I.waitForElement('.sidebar-nav', 5);
    },

    /**
     * Assert the switcher shows the current tenant name.
     */
    seeCurrentTenant(name) {
        I.see(name, this.switcherButton);
    },
};
