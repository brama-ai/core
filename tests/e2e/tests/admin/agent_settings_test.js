// E2E: Admin agent settings
// Verifies the agent settings page loads with iframe and config form.
// UC: CUJ-18 — Agent settings page shows config form and admin iframe.

Feature('Admin: Agent Settings');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'agent settings page is accessible from agents list',
    async ({ I, agentsPage, agentSettingsPage }) => {
        await agentsPage.open();
        I.see('Агенти');
        I.seeElement('table');

        I.see('Налаштування');
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'agent settings page shows configuration form',
    async ({ I, agentsPage, agentSettingsPage }) => {
        await agentsPage.open();

        const firstAgent = await I.grabTextFrom('table tbody tr:first-child td:first-child');
        if (firstAgent) {
            await agentSettingsPage.navigateToSettings(firstAgent.trim());

            agentSettingsPage.seeSettingsHeader();
            agentSettingsPage.seeConfigForm();
        }
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'agent settings page has description textarea',
    async ({ I, agentsPage, agentSettingsPage }) => {
        await agentsPage.open();

        const firstAgent = await I.grabTextFrom('table tbody tr:first-child td:first-child');
        if (firstAgent) {
            await agentSettingsPage.navigateToSettings(firstAgent.trim());
            I.seeElement('#configDescription');
        }
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'agent settings page has system prompt textarea',
    async ({ I, agentsPage, agentSettingsPage }) => {
        await agentsPage.open();

        const firstAgent = await I.grabTextFrom('table tbody tr:first-child td:first-child');
        if (firstAgent) {
            await agentSettingsPage.navigateToSettings(firstAgent.trim());
            I.seeElement('#configSystemPrompt');
        }
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'agent settings page shows Agent Card info',
    async ({ I, agentsPage, agentSettingsPage }) => {
        await agentsPage.open();

        const firstAgent = await I.grabTextFrom('table tbody tr:first-child td:first-child');
        if (firstAgent) {
            await agentSettingsPage.navigateToSettings(firstAgent.trim());
            agentSettingsPage.seeAgentCard();
        }
    },
).tag('@admin').tag('@agent').tag('@settings');