// E2E: Admin agents page
// Tests that the agents page shows both agents with healthy status
// after running discovery.

const assert = require('assert');

Feature('Admin: Agents Page');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'agents page is accessible and shows "Виявити агентів" button',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        I.see('Управління агентами');
        I.seeElement(agentsPage.discoverButton);
    },
).tag('@admin');

Scenario(
    'running discovery populates the registry',
    async ({ I, agentsPage }) => {
        await agentsPage.open();

        // Trigger discovery via the button
        I.click(agentsPage.discoverButton);

        // Wait for JS feedback message to appear
        await I.waitForText('Виявлено:', 10);

        // Wait for auto-reload
        await I.waitForElement('table tbody', 5);
    },
).tag('@admin');

Scenario(
    'knowledge-agent is present and healthy after discovery',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        agentsPage.seeAgent('knowledge-agent');
        agentsPage.seeAgentHealthy('knowledge-agent');
    },
).tag('@admin');

Scenario(
    'news-maker-agent is present and healthy after discovery',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        agentsPage.seeAgent('news-maker-agent');
        agentsPage.seeAgentHealthy('news-maker-agent');
    },
).tag('@admin');

Scenario(
    'no unexpected agents in registry (only known platform agents)',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        // test-agent must NOT appear (it is a functional test artifact)
        I.dontSee('test-agent', 'table');
    },
).tag('@admin');

Scenario(
    'health badge is green (badge-healthy) for all registered agents',
    async ({ I }) => {
        I.amOnPage('/admin/agents');
        await I.waitForElement('table tbody', 5);

        // There must be zero error/degraded/unavailable badges inside the agents table
        const errorBadges = await I.grabNumberOfVisibleElements('table .badge-error');
        const degradedBadges = await I.grabNumberOfVisibleElements('table .badge-degraded');
        const unavailableBadges = await I.grabNumberOfVisibleElements('table .badge-unavailable');

        assert.strictEqual(errorBadges, 0, `Expected 0 error badges, got ${errorBadges}`);
        assert.strictEqual(degradedBadges, 0, `Expected 0 degraded badges, got ${degradedBadges}`);
        assert.strictEqual(unavailableBadges, 0, `Expected 0 unavailable badges, got ${unavailableBadges}`);
    },
).tag('@admin');
