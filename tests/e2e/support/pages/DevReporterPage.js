const { I } = inject();

const DEV_REPORTER_URL = process.env.DEV_REPORTER_URL || 'http://localhost:18087';

module.exports = {
    adminUrl: `${DEV_REPORTER_URL}/admin/pipeline`,
    settingsPath: '/admin/agents/dev-reporter-agent/settings',

    // Selectors
    reportsTable: 'table',
    filterAll: 'a.filter-btn',
    statsRow: '.stats-row',

    async openDirect() {
        I.amOnPage(this.adminUrl);
        await I.waitForText('Pipeline Runs', 10);
    },

    async openViaSettings() {
        I.amOnPage(this.settingsPath);
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForText('Pipeline Runs', 10);
    },

    async switchOutOfIframe() {
        await I.switchTo();
    },

    seeReportsTable() {
        I.seeElement(this.reportsTable);
    },

    seeTableColumns() {
        I.see('Date');
        I.see('Task');
        I.see('Branch');
        I.see('Status');
        I.see('Duration');
        I.see('Agents');
    },

    seeStatsCards() {
        I.see('Total runs');
        I.see('Passed');
        I.see('Failed');
        I.see('Pass rate');
    },

    seeStatusFilters() {
        I.see('All');
        I.see('Passed');
        I.see('Failed');
    },
};
