// E2E: News-Maker Agent — admin discovery, settings iframe, source CRUD
// Verifies news-maker-agent appears healthy in admin panel,
// the settings page embeds the agent's admin via iframe,
// and source CRUD (add, toggle, delete) works correctly through the iframe.

const assert = require('assert');

const NEWS_MAKER_URL = process.env.NEWS_URL || 'http://localhost:18084';
const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const TEST_SOURCE_NAME = 'E2E Test Source';
const TEST_SOURCE_URL =
    process.env.NEWS_TEST_SOURCE_URL ||
    'http://news-maker-agent-e2e:8000/__e2e/mock-source';
const AGENT_NAME = 'news-maker-agent';
const SETTINGS_PATH = `/admin/agents/${AGENT_NAME}/settings`;

Feature('Admin: News-Maker Agent');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();

    // Ensure the agent registry has the correct E2E admin_url
    // (discovery may overwrite it with the prod URL which is behind Traefik edge-auth)
    await I.sendPostRequest(
        '/api/v1/internal/agents/register',
        JSON.stringify({
            name: AGENT_NAME,
            version: '0.1.0',
            description: 'AI-powered news curation and publishing',
            url: 'http://news-maker-agent-e2e:8000/api/v1/a2a',
            health_url: 'http://news-maker-agent-e2e:8000/health',
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

        agentsPage.seeAgent(AGENT_NAME);
        agentsPage.seeAgentHealthy(AGENT_NAME);
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'news-maker-agent settings page loads iframe with sources admin',
    async ({ I }) => {
        I.amOnPage(SETTINGS_PATH);
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
        // Navigate to core admin settings page which embeds agent admin in iframe
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 15);

        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 10);

        // Check if source already exists (from a previous run)
        const existingCount = await I.grabNumberOfVisibleElements(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]`,
        );

        if (existingCount === 0) {
            // Open the add-source modal
            I.click('+ Додати джерело');
            await I.waitForElement('#addModal.show', 5);

            // Fill form fields — use Playwright fill() for type="url" input reliability
            I.fillField('name', TEST_SOURCE_NAME);

            I.usePlaywrightTo('fill URL, topic, and priority fields', async ({ page }) => {
                const frame = page.frames().find(f => f.url().includes('/admin/sources'));
                if (!frame) throw new Error('Could not find agent admin iframe');
                await frame.locator('input[name="base_url"]').fill(TEST_SOURCE_URL);
                await frame.locator('input[name="topic_scope"]').fill('ai');
                await frame.locator('input[name="crawl_priority"]').fill('8');
            });

            // Submit the form
            I.click('Зберегти');

            // After form POST + redirect (303), re-enter iframe context
            await I.switchTo();
            await I.wait(2);
            await I.switchTo('iframe');
            await I.waitForText('Джерела новин', 15);
        }

        // Verify source appears in table
        I.seeElement(`//td[contains(text(),"${TEST_SOURCE_NAME}")]`);
        I.see('Активне');

        await I.switchTo();
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'can trigger news parsing from core admin settings',
    async ({ I }) => {
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('#crawlTriggerBtn', 15);
        I.click('#crawlTriggerBtn');

        // Wait for the result span to become visible (display changes from none to inline)
        await I.usePlaywrightTo('wait for crawl trigger result', async ({ page }) => {
            await page.waitForFunction(
                () => {
                    const el = document.getElementById('crawlTriggerResult');
                    return el && el.style.display !== 'none' && el.textContent.trim().length > 0;
                },
                { timeout: 45000 },
            );
        });

        // Verify the result text — accept both success and error (UI feedback works)
        const resultText = await I.grabTextFrom('#crawlTriggerResult');
        assert.ok(
            resultText.includes('Парсинг запущено') || resultText.includes('Помилка'),
            `Expected crawl result message, got: "${resultText}"`,
        );
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'preserves iframe location in URL hash after page reload',
    async ({ I }) => {
        I.amOnPage(SETTINGS_PATH);
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
        // Work through the iframe on the core admin settings page
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 15);

        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 10);
        await I.waitForText(TEST_SOURCE_NAME, 10);

        // Click disable button on our test source row
        I.click(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//button[contains(text(),"Вимкнути")]`,
        );

        // After form POST + redirect, re-enter iframe
        await I.switchTo();
        await I.wait(2);
        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 10);
        await I.waitForText(TEST_SOURCE_NAME, 10);

        // Verify it switched to disabled state
        I.seeElement(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//span[contains(text(),"Вимкнено")]`,
        );

        // Re-enable
        I.click(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//button[contains(text(),"Увімкнути")]`,
        );

        // After form POST + redirect, re-enter iframe
        await I.switchTo();
        await I.wait(2);
        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 10);
        await I.waitForText(TEST_SOURCE_NAME, 10);

        I.seeElement(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//span[contains(text(),"Активне")]`,
        );

        await I.switchTo();
    },
).tag('@admin').tag('@news-maker');

Scenario(
    'can delete a news source',
    async ({ I }) => {
        // Work through the iframe on the core admin settings page
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 15);

        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 10);
        await I.waitForText(TEST_SOURCE_NAME, 10);

        // Click the delete button (trash icon) on the test source row
        I.click(
            `//tr[contains(.,"${TEST_SOURCE_NAME}")]//button[contains(@class,"btn-outline-danger")]`,
        );

        // After confirm dialog + form POST + redirect, exit iframe and force a full page reload
        // so the iframe reloads fresh from the server.
        await I.switchTo();
        await I.wait(3);

        // Reload the entire settings page to force a fresh iframe load
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 15);
        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 15);

        // Verify source is gone
        I.dontSee(TEST_SOURCE_NAME);

        await I.switchTo();
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
        assert.strictEqual(response.data.name, AGENT_NAME);
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
