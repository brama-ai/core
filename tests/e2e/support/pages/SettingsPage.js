const { I } = inject();

module.exports = {
    url: '/admin/settings',

    settingsForm: 'form',
    logLevelSelect: '#log_level',
    retentionInput: '#retention_days',
    maxSizeInput: '#max_size_gb',
    saveButton: 'button[type="submit"]',
    savedMessage: '.glass-card p',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement(this.settingsForm, 10);
    },

    seeLogLevelSelector() {
        I.seeElement(this.logLevelSelect);
    },

    seeRetentionInput() {
        I.seeElement(this.retentionInput);
    },

    seeMaxSizeInput() {
        I.seeElement(this.maxSizeInput);
    },

    async selectLogLevel(level) {
        I.selectOption(this.logLevelSelect, level);
    },

    async setRetentionDays(days) {
        I.fillField(this.retentionInput, days);
    },

    async save() {
        I.click(this.saveButton);
        await I.waitForElement('.glass-card', 5);
    },

    seeSavedMessage() {
        I.see('Збережено');
    },
};