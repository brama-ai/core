const { I } = inject();

module.exports = {
    url: '/admin/dashboard',

    statsGrid: '.stats-grid',
    metricsGrid: '.metrics-grid',
    metricsSection: '.dash-section-title',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('.stats-grid', 10);
    },

    async seeMetricsSection() {
        I.seeElement(this.metricsSection);
        I.seeElement(this.metricsGrid);
    },

    seeA2AMetrics() {
        I.see('A2A Messages');
        I.see('Виклики за 24h');
        I.see('Виклики за 7d');
        I.see('Середній час відповіді');
        I.see('Успішність');
    },

    seeAgentActivity() {
        I.see('Активність агентів');
        I.see('Активних за 24h');
    },

    seeSchedulerStats() {
        I.see('Планувальник');
        I.see('Активних задач');
        I.see('Призупинених');
    },

    seeMetricValue(selector, value) {
        I.see(value, selector);
    },
};