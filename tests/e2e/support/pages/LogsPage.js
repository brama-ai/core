const { I } = inject();

module.exports = {
    url: '/admin/logs',

    filterForm: '.log-filter-form',
    searchInput: '#q',
    levelSelect: '#level',
    appSelect: '#app',
    categorySelect: '#category',
    submitButton: '.log-filter-form button[type="submit"]',
    resultsTable: 'table.admin-table tbody',
    totalCount: '.glass-card span',
    paginationBlock: '.log-pagination',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement(this.filterForm, 10);
    },

    async search(query) {
        I.fillField(this.searchInput, query);
        I.click(this.submitButton);
        await I.waitForElement(this.resultsTable, 10);
    },

    async filterByLevel(level) {
        I.selectOption(this.levelSelect, level);
        I.click(this.submitButton);
        await I.waitForElement(this.resultsTable, 10);
    },

    async filterByApp(app) {
        I.selectOption(this.appSelect, app);
        I.click(this.submitButton);
        await I.waitForElement(this.resultsTable, 10);
    },

    async filterByCategory(category) {
        I.selectOption(this.categorySelect, category);
        I.click(this.submitButton);
        await I.waitForElement(this.resultsTable, 10);
    },

    seeLogEntry() {
        I.seeElement('table.admin-table tbody tr');
    },

    seeLevelBadge(level) {
        I.seeElement(`.badge-log-${level.toLowerCase()}`);
    },

    seeTraceLink() {
        I.seeElement('table.admin-table tbody tr td a[href*="/admin/logs/trace/"]');
    },

    async clickFirstTraceLink() {
        I.click('table.admin-table tbody tr:first-child td a[href*="/admin/logs/trace/"]');
        await I.waitForElement('.trace-timeline', 10);
    },
};
