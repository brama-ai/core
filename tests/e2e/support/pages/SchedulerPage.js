const { I } = inject();

module.exports = {
    url: '/admin/scheduler',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('table', 10);
    },

    seeJob(agentName, jobName) {
        I.see(agentName, 'table');
        I.see(jobName, 'table');
    },

    seeJobEnabled(jobName) {
        I.seeElement(`//tr[contains(., '${jobName}')]//span[contains(@class, 'badge-log-info') and contains(text(), 'так')]`);
    },

    seeRunButton(jobName) {
        I.seeElement(`//tr[contains(., '${jobName}')]//button[contains(text(), 'Запустити')]`);
    },

    seeToggleButton(jobName) {
        I.seeElement(`//tr[contains(., '${jobName}')]//button[contains(text(), 'Вимкнути') or contains(text(), 'Увімкнути')]`);
    },
};
