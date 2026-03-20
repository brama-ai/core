const { I } = inject();

module.exports = {
    url: '/admin/logs',

    traceContainer: '.trace-sequence',
    waterfallSection: '.trace-waterfall',
    timelineSection: '.trace-timeline',
    diagramView: '#trace-view-diagram',
    classicView: '#trace-view-classic',
    diagramButton: '[data-trace-view-button="diagram"]',
    classicButton: '[data-trace-view-button="classic"]',

    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('.log-filter-form', 10);
    },

    async navigateToTrace(traceId) {
        I.click(`a[href*="/admin/logs/trace/${traceId}"]`);
        await I.waitForElement(this.traceContainer, 10);
    },

    seeTraceContainer() {
        I.seeElement(this.traceContainer);
    },

    seeWaterfall() {
        I.seeElement(this.waterfallSection);
    },

    seeTimeline() {
        I.seeElement(this.timelineSection);
    },

    seeSequenceDiagram() {
        I.seeElement('.sequence-diagram');
        I.seeElement('.sequence-participants');
    },

    seeSpans() {
        I.seeElement('.sequence-event');
    },

    async switchToClassicView() {
        I.click(this.classicButton);
        await I.waitForElement(this.classicView + '.active', 3);
    },

    async switchToDiagramView() {
        I.click(this.diagramButton);
        await I.waitForElement(this.diagramView + '.active', 3);
    },

    seeCallEvent(operation) {
        I.see(operation, '.sequence-arrow-label');
    },

    seeParticipant(name) {
        I.see(name, '.sequence-participant');
    },

    async openEventDetails(index) {
        I.click(`.sequence-detail-icon`);
        await I.waitForElement('.sequence-detail-panel.active', 3);
    },

    seeLogGroups() {
        I.seeElement('.trace-timeline details');
    },
};