const { I } = inject();

module.exports = {
    url: '/admin/chats',

    filterForm: '.log-filter-form',
    agentInput: 'input[name="agent"]',
    statusInput: 'input[name="status"]',
    submitButton: '.log-filter-form button[type="submit"]',
    chatsTable: 'table.admin-table',
    tableBody: 'table.admin-table tbody',
    emptyState: '.stub-content',
    paginationBlock: '.log-pagination',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('.glass-card', 10);
    },

    async filterByAgent(agent) {
        I.fillField(this.agentInput, agent);
        I.click(this.submitButton);
        await I.waitForElement('.glass-card', 10);
    },

    async filterByStatus(status) {
        I.fillField(this.statusInput, status);
        I.click(this.submitButton);
        await I.waitForElement('.glass-card', 10);
    },

    seeChat() {
        I.seeElement(`${this.tableBody} tr`);
    },

    seeChatWithAgent(agentName) {
        I.see(agentName, this.chatsTable);
    },

    async clickFirstChat() {
        I.click(`${this.tableBody} tr:first-child`);
        await I.waitForElement('.glass-card', 10);
    },

    async clickFirstTraceLink() {
        I.click(`${this.tableBody} tr:first-child td a.chat-trace-link`);
        await I.waitForElement('.trace-timeline', 10);
    },
};
