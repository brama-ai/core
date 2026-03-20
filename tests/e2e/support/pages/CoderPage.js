const { I } = inject();

module.exports = {
    url: '/admin/coder',

    statsGrid: '.stats-grid',
    taskTable: 'table tbody',
    createButton: 'a[href*="/admin/coder/create"]',
    workerPanel: '.card',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement(this.statsGrid, 10);
    },

    seeStats() {
        I.seeElement(this.statsGrid);
        I.see('Завдання');
    },

    seeTaskList() {
        I.seeElement(this.taskTable);
    },

    seeEmptyState() {
        I.see('Немає завдань');
    },

    async clickCreate() {
        I.click(this.createButton);
        await I.waitForElement('form', 5);
    },

    seeTask(title) {
        I.see(title, this.taskTable);
    },

    seeTaskStatus(title, status) {
        I.seeElement(`//tr[contains(., "${title}")]//span[contains(@class, "badge") and contains(text(), "${status}")]`);
    },

    async clickTask(title) {
        I.click(locate('a').withText(title).inside('table'));
        await I.waitForElement('.card', 5);
    },

    seeWorkers() {
        I.see('Працівники');
    },

    seeWorkerStatus(workerId, status) {
        I.see(workerId, '.card');
        I.see(status, '.card .badge');
    },

    seeRecentActivity() {
        I.see('Остання активність');
    },
};