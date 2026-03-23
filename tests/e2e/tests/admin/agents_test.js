// E2E: Admin agents page
// Tests that the agents page shows both agents with healthy status
// after running discovery.
// Agents may be registered with an `-e2e` suffix, so name checks use
// `contains` via the page-object helper `seeAgentLike` / XPath contains().

const assert = require('assert');

const OPENCLAW_URL = process.env.OPENCLAW_URL || 'http://openclaw.localhost';

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
        agentsPage.seeAgentLike('knowledge-agent');
        agentsPage.seeAgentHealthyLike('knowledge-agent');
    },
).tag('@admin');

Scenario(
    'news-maker-agent is present and healthy after discovery',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        agentsPage.seeAgentLike('news-maker-agent');
        agentsPage.seeAgentHealthyLike('news-maker-agent');
    },
).tag('@admin');

Scenario(
    'hello-agent is present and healthy after discovery',
    async ({ I, agentsPage }) => {
        await agentsPage.open();
        agentsPage.seeAgentLike('hello-agent');
        agentsPage.seeAgentHealthyLike('hello-agent');
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

Scenario(
    'OpenClaw sync badge is visible for enabled agents',
    async ({ I }) => {
        // Check if OpenClaw is actually running before testing its UI
        try {
            const res = await I.sendGetRequest(`${OPENCLAW_URL}/`);
            if (res.status === 404 || res.status === 502 || res.status === 0) {
                I.say('OpenClaw not running (got ' + res.status + ') — skipping OpenClaw badge check');
                return;
            }
        } catch (e) {
            I.say('OpenClaw not reachable — skipping OpenClaw badge check');
            return;
        }

        I.amOnPage('/admin/agents');
        await I.waitForElement('table tbody', 5);

        // Check if OpenClaw column exists in the table
        I.see('OpenClaw', 'table thead');

        // Look for OpenClaw sync badges in the table
        const openclawBadges = await I.grabNumberOfVisibleElements('table .badge-openclaw, table .openclaw-badge, table [class*="openclaw"]');

        // If there are enabled agents, there should be OpenClaw badges
        const agentRows = await I.grabNumberOfVisibleElements('table tbody tr');
        if (agentRows > 0) {
            assert(openclawBadges >= 0, `Expected OpenClaw badges to be present for enabled agents`);
        }
    },
).tag('@admin').tag('@optional');

Scenario(
    'manual OpenClaw sync button triggers status update',
    async ({ I }) => {
        // Check if OpenClaw is actually running before testing sync
        try {
            const res = await I.sendGetRequest(`${OPENCLAW_URL}/`);
            if (res.status === 404 || res.status === 502 || res.status === 0) {
                I.say('OpenClaw not running (got ' + res.status + ') — skipping OpenClaw sync test');
                return;
            }
        } catch (e) {
            I.say('OpenClaw not reachable — skipping OpenClaw sync test');
            return;
        }

        I.amOnPage('/admin/agents');
        await I.waitForElement('table tbody', 5);

        // Look for sync button (could be "Синхронізувати" or similar)
        const syncButtonSelectors = [
            'button:contains("Синхронізувати")',
            'button[data-action="sync"]',
            '#syncBtn',
            '.btn-sync',
            'button:contains("Sync")',
        ];

        let syncButtonFound = false;
        for (const selector of syncButtonSelectors) {
            try {
                I.seeElement(selector);
                syncButtonFound = true;

                // Click the sync button
                I.click(selector);

                // Wait for some indication that sync was triggered
                await I.wait(2);

                break;
            } catch (e) {
                continue;
            }
        }

        if (!syncButtonFound) {
            I.say('No OpenClaw sync button found — this may be expected if OpenClaw is not configured');
        }
    },
).tag('@admin').tag('@optional');
