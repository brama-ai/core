const { I } = inject();

module.exports = {
    url: '/admin/agents',

    agentSettingsLink: '//a[contains(@href, "/settings")]',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('table', 10);
    },

    async navigateToSettings(agentName) {
        I.click(locate('a').withText('Налаштування').inside(`//tr[contains(., "${agentName}")]`));
        await I.waitForElement('.iframe-header', 10);
    },

    seeSettingsHeader() {
        I.see('Налаштування');
    },

    seeAgentStatus(enabled) {
        const badge = enabled ? 'Увімкнено' : 'Вимкнено';
        I.see(badge, '.badge');
    },

    seeConfigForm() {
        I.seeElement('#configForm');
        I.seeElement('#configDescription');
        I.seeElement('#configSystemPrompt');
    },

    seeAgentCard() {
        I.see('Agent Card');
    },

    seeAdminIframe() {
        I.seeElement('#agentAdminFrame');
    },

    async saveConfig() {
        I.click('#configSaveBtn');
        await I.waitForText('Збережено', 5);
    },

    setDescription(text) {
        I.fillField('#configDescription', text);
    },

    setSystemPrompt(text) {
        I.fillField('#configSystemPrompt', text);
    },
};