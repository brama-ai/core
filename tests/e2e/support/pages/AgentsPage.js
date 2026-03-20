const { I } = inject();

module.exports = {
    url: '/admin/agents',

    discoverButton: '#discoverBtn',
    syncButton: 'button:contains("Синхронізувати"), button[data-action="sync"], #syncBtn, .btn-sync',
    installedTabButton: '#tab-installed-btn',
    marketplaceTabButton: '#tab-marketplace-btn',
    agentsTable: 'table tbody',
    emptyState: '.empty-row',

    activePaneRow(agentName) {
        return `//div[contains(@class,"agent-tab-pane") and contains(@class,"active")]//tr[@data-agent-name="${agentName}"]`;
    },

    /**
     * Navigate to the agents page.
     */
    async open() {
        I.amOnPage(this.url);
        await I.waitForElement('table', 10);
    },

    async switchToInstalled() {
        I.click(this.installedTabButton);
        await I.waitForElement('#tab-installed.agent-tab-pane.active', 5);
    },

    async switchToMarketplace() {
        I.click(this.marketplaceTabButton);
        await I.waitForElement('#tab-marketplace.agent-tab-pane.active', 5);
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
        const row = this.activePaneRow(agentName);
        return I.grabTextFrom(`${row}//span[contains(@class,"badge-healthy") or contains(@class,"badge-degraded") or contains(@class,"badge-unavailable") or contains(@class,"badge-error") or contains(@class,"badge-unknown")]`);
    },

    /**
     * Assert that an agent row exists.
     */
    seeAgent(agentName) {
        const row = this.activePaneRow(agentName);
        I.seeElement(row);
    },

    /**
     * Assert that an agent has the healthy badge.
     */
    seeAgentHealthy(agentName) {
        const row = this.activePaneRow(agentName);
        I.seeElement(row);
        I.seeElement(`${row}//span[contains(@class,"badge-healthy")]`);
    },

    /**
     * Assert that an agent is in enabled state.
     */
    seeAgentEnabled(agentName) {
        const row = this.activePaneRow(agentName);
        I.seeElement(`${row}//span[contains(@class,"badge-enabled")]`);
    },

    /**
     * Assert that an agent is in disabled state.
     */
    seeAgentDisabled(agentName) {
        const row = this.activePaneRow(agentName);
        I.seeElement(`${row}//span[contains(@class,"badge-disabled")]`);
    },

    /**
     * Click the disable button for a given agent.
     * Handles the confirm dialog automatically.
     */
    async disableAgent(agentName) {
        const row = this.activePaneRow(agentName);
        I.click(`${row}//button[contains(@class,"btn-disable")]`);
    },

    /**
     * Click the enable button for a given agent.
     * Handles the confirm dialog automatically.
     */
    async enableAgent(agentName) {
        const row = this.activePaneRow(agentName);
        I.click(`${row}//button[contains(@class,"btn-enable")]`);
    },

    /**
     * Assert that an install button is visible for a given agent.
     */
    seeInstallButton(agentName) {
        const row = this.activePaneRow(agentName);
        I.seeElement(`${row}//button[contains(@class,"btn-install")]`);
    },

    /**
     * Click the install button for a given agent.
     */
    async installAgent(agentName) {
        const row = this.activePaneRow(agentName);
        I.click(`${row}//button[contains(@class,"btn-install")]`);
    },

    /**
     * Assert that a delete button is visible for a given agent.
     */
    seeDeleteButton(agentName) {
        const row = this.activePaneRow(agentName);
        I.seeElement(`${row}//button[contains(@class,"btn-delete")]`);
    },

    /**
     * Assert that no delete button is visible for a given agent.
     */
    dontSeeDeleteButton(agentName) {
        const row = this.activePaneRow(agentName);
        I.dontSeeElement(`${row}//button[contains(@class,"btn-delete")]`);
    },

    /**
     * Click the delete button for a given agent.
     * Confirm dialog must be auto-accepted beforehand.
     */
    async deleteAgent(agentName) {
        const row = this.activePaneRow(agentName);
        I.click(`${row}//button[contains(@class,"btn-delete")]`);
    },

    /**
     * Check if OpenClaw sync button is visible.
     */
    seeOpenClawSyncButton() {
        I.seeElement(this.syncButton);
    },

    /**
     * Click the OpenClaw sync button and wait for response.
     */
    async triggerOpenClawSync() {
        I.click(this.syncButton);
        await I.wait(2); // Wait for sync to complete
    },

    /**
     * Assert that an agent has an OpenClaw sync badge.
     */
    seeOpenClawBadge(agentName) {
        const row = this.activePaneRow(agentName);
        I.seeElement(`${row}//span[contains(@class,"badge-openclaw") or contains(@class,"openclaw-badge") or contains(@class,"badge") and contains(text(),"OpenClaw")]`);
    },

    /**
     * Get the OpenClaw sync status for a given agent.
     */
    async getOpenClawStatus(agentName) {
        const row = this.activePaneRow(agentName);
        try {
            return await I.grabTextFrom(`${row}//span[contains(@class,"badge-openclaw") or contains(@class,"openclaw-badge")]`);
        } catch (e) {
            return null; // No OpenClaw badge found
        }
    },
};
