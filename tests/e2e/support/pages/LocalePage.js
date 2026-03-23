const { I } = inject();

module.exports = {
    url: '/admin/dashboard',

    switcherButton: '#localeSwitcher > button',
    localeDropdown: '#localeDropdown',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('.sidebar-nav', 10);
    },

    seeSwitcher() {
        I.seeElement(this.switcherButton);
    },

    async openSwitcher() {
        I.click(this.switcherButton);
        await I.waitForElement('#localeDropdown.show', 3);
    },

    async switchToLocale(locale) {
        await this.openSwitcher();
        I.click(locate('button').withAttr({ name: 'locale', value: locale }).inside(this.localeDropdown));
        await I.waitForElement('.sidebar-nav', 10);
    },

    seeCurrentLocale(locale) {
        const label = locale === 'uk' ? 'UA' : 'EN';
        I.see(label, this.switcherButton);
    },
};