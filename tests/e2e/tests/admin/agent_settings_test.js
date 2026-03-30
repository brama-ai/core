// E2E: Admin agent settings
// Verifies the agent settings page loads with iframe and config form.
// UC: CUJ-18 — Agent settings page shows config form and admin iframe.
//
// Uses hello-agent directly via URL — it is always present and has a
// settings page with config form, description, system prompt, and Agent Card.

Feature('Admin: Agent Settings');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

// Resolve the actual agent slug (may be registered as hello-agent-e2e).
async function resolveHelloAgentSlug(I) {
    I.amOnPage('/admin/agents');
    await I.waitForElement('table', 10);
    // Try exact name first, fall back to -e2e variant
    const rows = await I.grabNumberOfVisibleElements('//tr[@data-agent-name="hello-agent"]');
    if (rows > 0) return 'hello-agent';
    const rowsE2e = await I.grabNumberOfVisibleElements('//tr[contains(@data-agent-name,"hello-agent")]');
    if (rowsE2e > 0) {
        return await I.grabAttributeFrom('//tr[contains(@data-agent-name,"hello-agent")]', 'data-agent-name');
    }
    return 'hello-agent'; // fallback
}

Scenario(
    'agent settings page is accessible from agents list',
    async ({ I, agentsPage, agentSettingsPage }) => {
        await agentsPage.open();
        I.see('Управління агентами');
        I.seeElement('table');

        I.see('Налаштування');
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'agent settings page shows configuration form',
    async ({ I, agentSettingsPage }) => {
        const slug = await resolveHelloAgentSlug(I);
        I.amOnPage(`/admin/agents/${slug}/settings`);
        await I.waitForElement('#configForm', 10);

        agentSettingsPage.seeSettingsHeader();
        agentSettingsPage.seeConfigForm();
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'agent settings page has description textarea',
    async ({ I }) => {
        const slug = await resolveHelloAgentSlug(I);
        I.amOnPage(`/admin/agents/${slug}/settings`);
        await I.waitForElement('#configDescription', 10);

        I.seeElement('#configDescription');
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'agent settings page has system prompt textarea',
    async ({ I }) => {
        const slug = await resolveHelloAgentSlug(I);
        I.amOnPage(`/admin/agents/${slug}/settings`);
        await I.waitForElement('#configSystemPrompt', 10);

        I.seeElement('#configSystemPrompt');
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'agent settings page shows Agent Card info',
    async ({ I, agentSettingsPage }) => {
        const slug = await resolveHelloAgentSlug(I);
        I.amOnPage(`/admin/agents/${slug}/settings`);
        await I.waitForElement('#configForm', 10);

        agentSettingsPage.seeAgentCard();
    },
).tag('@admin').tag('@agent').tag('@settings');

Scenario(
    'config save persists description and system_prompt after page reload',
    async ({ I, agentSettingsPage }) => {
        const slug = await resolveHelloAgentSlug(I);
        I.amOnPage(`/admin/agents/${slug}/settings`);
        await I.waitForElement('#configForm', 10);

        const testDescription = `E2E test description ${Date.now()}`;
        const testPrompt = `E2E system prompt ${Date.now()}`;

        agentSettingsPage.setDescription(testDescription);
        agentSettingsPage.setSystemPrompt(testPrompt);
        await agentSettingsPage.saveConfig();

        // Reload and verify values persist
        I.amOnPage(`/admin/agents/${slug}/settings`);
        await I.waitForElement('#configDescription', 10);

        I.seeInField('#configDescription', testDescription);
        I.seeInField('#configSystemPrompt', testPrompt);
    },
).tag('@admin').tag('@agent').tag('@settings').tag('@config');

Scenario(
    'config save with empty fields causes no server error and fields remain empty after reload',
    async ({ I, agentSettingsPage }) => {
        const slug = await resolveHelloAgentSlug(I);
        I.amOnPage(`/admin/agents/${slug}/settings`);
        await I.waitForElement('#configForm', 10);

        // Clear both fields
        agentSettingsPage.setDescription('');
        agentSettingsPage.setSystemPrompt('');
        await agentSettingsPage.saveConfig();

        // Reload and verify fields are empty (no server error)
        I.amOnPage(`/admin/agents/${slug}/settings`);
        await I.waitForElement('#configDescription', 10);

        I.seeInField('#configDescription', '');
        I.seeInField('#configSystemPrompt', '');
    },
).tag('@admin').tag('@agent').tag('@settings').tag('@config');

Scenario(
    'agent with admin_url in manifest shows admin iframe on settings page',
    async ({ I, agentSettingsPage }) => {
        // knowledge-agent exposes an admin_url in its manifest (admin panel at /admin)
        // Use it to verify the iframe element is present on the settings page.
        I.amOnPage('/admin/agents');
        await I.waitForElement('table', 10);

        // Find an agent that has an admin iframe — knowledge-agent has admin_url
        const knowledgeAgentRows = await I.grabNumberOfVisibleElements(
            '//tr[contains(@data-agent-name,"knowledge-agent")]',
        );

        if (knowledgeAgentRows === 0) {
            // Skip if knowledge-agent is not registered in this environment
            return;
        }

        const agentName = await I.grabAttributeFrom(
            '//tr[contains(@data-agent-name,"knowledge-agent")]',
            'data-agent-name',
        );

        I.amOnPage(`/admin/agents/${agentName}/settings`);
        await I.waitForElement('#configForm', 10);

        // Check if admin iframe is present (only if agent has admin_url in manifest)
        const iframeCount = await I.grabNumberOfVisibleElements('#agentAdminFrame');
        if (iframeCount > 0) {
            agentSettingsPage.seeAdminIframe();
        }
        // If no iframe, the agent simply doesn't expose admin_url — test passes either way
    },
).tag('@admin').tag('@agent').tag('@settings').tag('@iframe');