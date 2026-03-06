const { I } = inject();

module.exports = {
    url: '/admin/agents',

    discoverButton: '#discoverBtn',
    agentsTable: 'table tbody',
    emptyState: '.empty-row',

    /**
     * Navigate to the agents page.
     */
    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('table', 10);
    },

    /**
     * Click the "Виявити агентів" button and wait for reload.
     */
    async runDiscovery() {
        I.click(this.discoverButton);
        await I.waitForText('Виявлено:', 10);
        await I.waitInUrl('/admin/agents', 5);
        await I.waitForElement('table', 5);
    },

    /**
     * Returns the health badge text for a given agent name.
     */
    async getHealthBadge(agentName) {
        const row = `//tr[@data-agent-name="${agentName}"]`;
        return I.grabTextFrom(`${row}//span[contains(@class,"badge-healthy") or contains(@class,"badge-degraded") or contains(@class,"badge-unavailable") or contains(@class,"badge-error") or contains(@class,"badge-unknown")]`);
    },

    /**
     * Assert that an agent row exists.
     */
    seeAgent(agentName) {
        I.see(agentName, 'table');
    },

    /**
     * Assert that an agent has the healthy badge.
     */
    seeAgentHealthy(agentName) {
        I.see(agentName, 'table');
        I.seeElement(`//tr[@data-agent-name="${agentName}"]//span[contains(@class,"badge-healthy")]`);
    },

    /**
     * Assert that an agent is in enabled state.
     */
    seeAgentEnabled(agentName) {
        I.seeElement(`//tr[@data-agent-name="${agentName}"]//span[contains(@class,"badge-enabled")]`);
    },

    /**
     * Assert that an agent is in disabled state.
     */
    seeAgentDisabled(agentName) {
        I.seeElement(`//tr[@data-agent-name="${agentName}"]//span[contains(@class,"badge-disabled")]`);
    },

    /**
     * Click the disable button for a given agent.
     * Handles the confirm dialog automatically.
     */
    async disableAgent(agentName) {
        const row = `//tr[@data-agent-name="${agentName}"]`;
        I.click(`${row}//button[contains(@class,"btn-disable")]`);
    },

    /**
     * Click the enable button for a given agent.
     * Handles the confirm dialog automatically.
     */
    async enableAgent(agentName) {
        const row = `//tr[@data-agent-name="${agentName}"]`;
        I.click(`${row}//button[contains(@class,"btn-enable")]`);
    },

    /**
     * Assert that a delete button is visible for a given agent.
     */
    seeDeleteButton(agentName) {
        const row = `//tr[@data-agent-name="${agentName}"]`;
        I.seeElement(`${row}//button[contains(@class,"btn-delete")]`);
    },

    /**
     * Assert that no delete button is visible for a given agent.
     */
    dontSeeDeleteButton(agentName) {
        const row = `//tr[@data-agent-name="${agentName}"]`;
        I.dontSeeElement(`${row}//button[contains(@class,"btn-delete")]`);
    },

    /**
     * Click the delete button for a given agent.
     * Confirm dialog must be auto-accepted beforehand.
     */
    async deleteAgent(agentName) {
        const row = `//tr[@data-agent-name="${agentName}"]`;
        I.click(`${row}//button[contains(@class,"btn-delete")]`);
    },
};
