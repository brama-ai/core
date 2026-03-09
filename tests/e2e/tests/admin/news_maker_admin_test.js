// E2E: News-Maker Agent — admin discovery, settings iframe, source CRUD
// Verifies news-maker-agent appears healthy in admin panel,
// the settings page embeds the agent's admin via iframe,
// and source CRUD (add, toggle, delete) works correctly.

const assert = require('assert');

const NEWS_MAKER_URL = process.env.NEWS_URL || 'http://localhost:18084';
const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const TEST_SOURCE_NAME = 'E2E Test Source';
const TEST_SOURCE_URL =
    process.env.NEWS_TEST_SOURCE_URL ||
    'http://news-maker-agent-e2e:8000/__e2e/mock-source';

Feature('Admin: News-Maker Agent');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();

    // Ensure the agent registry has the correct E2E admin_url
    // (discovery may overwrite it with the prod URL which is behind Traefik edge-auth)
    await I.sendPostRequest(
        '/api/v1/internal/agents/register',
        JSON.stringify({
            name: 'news-maker-agent',
            version: '0.1.0',
            description: 'AI-powered news curation and publishing',
            url: 'http://news-maker-agent-e2e:8000/api/v1/a2a',
            admin_url: `${NEWS_MAKER_URL}/admin/sources`,
            skills: [
                { id: 'news.publish', name: 'News Publish', description: 'Publish curated news content' },
                { id: 'news.curate', name: 'News Curate', description: 'Curate and summarize news articles' },
            ],
        }),
        { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
    );

    // Accept all confirm() dialogs automatically
    I.usePlaywrightTo('auto-accept dialogs', async ({ page }) => {
        page.on('dialog', async (dialog) => {
            await dialog.accept();
        });
    });
});

Scenario(
    'news-maker-agent is present and healthy after discovery',
    async ({ I, agentsPage }) => {
        await agentsPage.open();

        // Re-discover agents to pick up latest manifest (with admin_url + storage)
        await agentsPage.runDiscovery();

        agentsPage.seeAgent('news-maker-agent');
        agentsPage.seeAgentHealthy('news-maker-agent');
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'news-maker-agent settings page loads iframe with sources admin',
    async ({ I }) => {
        I.amOnPage('/admin/agents/news-maker-agent/settings');
        await I.waitForElement('iframe', 10);
        I.seeElement('iframe');

        // Switch into iframe context
        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 10);
        I.see('Джерела новин');
        I.see('Додати джерело');
        await I.switchTo();
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'can add a news source via the admin panel',
    async ({ I }) => {
        // Navigate directly to the news-maker admin (edge auth required)
        await I.ensureEdgeAccess(`${NEWS_MAKER_URL}/admin/sources`);
        I.amOnPage(`${NEWS_MAKER_URL}/admin/sources`);
        await I.waitForText('Джерела новин', 10);

        // Open the add-source modal
        I.click('+ Додати джерело');
        await I.waitForElement('#addModal.show', 5);

        // Fill form fields
        I.fillField('name', TEST_SOURCE_NAME);
        I.fillField('base_url', TEST_SOURCE_URL);
        I.fillField('topic_scope', 'ai');
        I.fillField('crawl_priority', '8');

        // Submit
        I.click('Зберегти');

        // Verify source appears in table
        await I.waitForText(TEST_SOURCE_NAME, 10);
        I.see(TEST_SOURCE_NAME);
        I.see(TEST_SOURCE_URL);
        I.see('Активне');
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'can trigger news parsing from core admin settings',
    async ({ I }) => {
        I.amOnPage('/admin/agents/news-maker-agent/settings');
        await I.waitForElement('#crawlTriggerBtn', 10);
        I.click('#crawlTriggerBtn');
        await I.waitForText('Парсинг запущено', 20, '#crawlTriggerResult');
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'preserves iframe location in URL hash after page reload',
    async ({ I }) => {
        I.amOnPage('/admin/agents/news-maker-agent/settings');
        await I.waitForElement('iframe', 10);

        await I.switchTo('iframe');
        I.click('Налаштування');
        await I.waitForText('Налаштування агента', 10);
        await I.switchTo();

        const urlBeforeRefresh = await I.grabCurrentUrl();
        assert.ok(
            urlBeforeRefresh.includes('agent_admin_path='),
            'parent URL must persist iframe path in hash',
        );

        I.refreshPage();
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForText('Налаштування агента', 10);
        await I.switchTo();
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'can toggle source enabled/disabled',
    async ({ I }) => {
        await I.ensureEdgeAccess(`${NEWS_MAKER_URL}/admin/sources`);
        I.amOnPage(`${NEWS_MAKER_URL}/admin/sources`);
        await I.waitForText(TEST_SOURCE_NAME, 10);

        // Click disable button on our test source row
        I.click(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//button[contains(text(),"Вимкнути")]`,
        );

        // Verify it switched to disabled state
        await I.waitForText(TEST_SOURCE_NAME, 10);
        I.seeElement(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//span[contains(text(),"Вимкнено")]`,
        );

        // Re-enable
        I.click(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//button[contains(text(),"Увімкнути")]`,
        );
        await I.waitForText(TEST_SOURCE_NAME, 10);
        I.seeElement(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//span[contains(text(),"Активне")]`,
        );
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'can delete a news source',
    async ({ I }) => {
        await I.ensureEdgeAccess(`${NEWS_MAKER_URL}/admin/sources`);
        I.amOnPage(`${NEWS_MAKER_URL}/admin/sources`);
        await I.waitForText(TEST_SOURCE_NAME, 10);

        // Click the delete button (trash icon) on the test source row
        I.click(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//button[contains(@class,"btn-outline-danger")]`,
        );

        // Wait for page to reload after deletion
        await I.waitForElement('table', 10);
        I.dontSee(TEST_SOURCE_NAME);
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'news-maker-agent health endpoint returns ok',
    async ({ I }) => {
        const cookieName =
            process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';
        await I.ensureEdgeAccess(`${NEWS_MAKER_URL}/health`);
        const edgeCookie = await I.grabCookie(cookieName);
        if (!edgeCookie || !edgeCookie.value) {
            throw new Error(
                `Expected ${cookieName} cookie for health request`,
            );
        }
        I.haveRequestHeaders({
            Cookie: `${cookieName}=${edgeCookie.value}`,
        });

        const response = await I.sendGetRequest(
            `${NEWS_MAKER_URL}/health`,
        );
        assert.strictEqual(response.status, 200);
        assert.strictEqual(response.data.status, 'ok');
        assert.strictEqual(response.data.service, 'news-maker-agent');
    },
).tag('@smoke').tag('@news-maker');

Scenario(
    'news-maker-agent manifest includes admin_url and storage',
    async ({ I }) => {
        const cookieName =
            process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';
        await I.ensureEdgeAccess(`${NEWS_MAKER_URL}/api/v1/manifest`);
        const edgeCookie = await I.grabCookie(cookieName);
        if (!edgeCookie || !edgeCookie.value) {
            throw new Error(
                `Expected ${cookieName} cookie for manifest request`,
            );
        }
        I.haveRequestHeaders({
            Cookie: `${cookieName}=${edgeCookie.value}`,
        });

        const response = await I.sendGetRequest(
            `${NEWS_MAKER_URL}/api/v1/manifest`,
        );
        assert.strictEqual(response.status, 200);
        assert.strictEqual(response.data.name, 'news-maker-agent');
        assert.ok(response.data.admin_url, 'manifest must include admin_url');
        assert.ok(
            response.data.admin_url.includes('/admin/sources'),
            'admin_url must point to sources admin',
        );
        assert.ok(response.data.storage, 'manifest must include storage');
        assert.ok(
            response.data.storage.postgres,
            'storage must include postgres',
        );
        assert.ok(
            Array.isArray(response.data.skills),
            'skills must be an array',
        );
        assert.ok(
            response.data.skills.some((s) => s.id === 'news.publish'),
            'skills must contain news.publish',
        );
        assert.ok(
            response.data.skills.some((s) => s.id === 'news.curate'),
            'skills must contain news.curate',
        );
    },
).tag('@smoke').tag('@news-maker');
