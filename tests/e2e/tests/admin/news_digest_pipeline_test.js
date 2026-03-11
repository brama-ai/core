// E2E: News-Maker Agent — full crawl pipeline with real source
// 1. Enable agent, set description + system prompt
// 2. Add TechCrunch AI source via iframe
// 3. Trigger crawl, wait for completion
// 4. Verify curated news items visible with paginator

const assert = require('assert');

const NEWS_MAKER_URL = process.env.NEWS_URL || 'http://localhost:18084';
const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const AGENT_NAME = 'news-maker-agent';
const TECHCRUNCH_URL = 'https://techcrunch.com/category/artificial-intelligence/';
const SOURCE_NAME = 'TechCrunch AI';

Feature('News Digest Pipeline');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();

    // Re-register agent with E2E admin_url
    await I.sendPostRequest(
        '/api/v1/internal/agents/register',
        JSON.stringify({
            name: AGENT_NAME,
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

    // Accept all confirm() dialogs
    I.usePlaywrightTo('auto-accept dialogs', async ({ page }) => {
        page.on('dialog', async (dialog) => {
            await dialog.accept();
        });
    });
});

Scenario(
    'step 1: enable agent and configure system prompts',
    async ({ I, agentsPage }) => {
        // Ensure agent is discovered
        await agentsPage.open();
        await agentsPage.runDiscovery();
        agentsPage.seeAgent(AGENT_NAME);

        // Enable agent if disabled
        const isDisabled = await I.grabNumberOfVisibleElements(
            `${agentsPage.activePaneRow(AGENT_NAME)}//button[contains(@class,"btn-enable")]`,
        );
        if (isDisabled > 0) {
            await agentsPage.enableAgent(AGENT_NAME);
            await I.waitForElement('table', 10);
        }

        // Go to agent settings page
        I.amOnPage(`/admin/agents/${AGENT_NAME}/settings`);
        await I.waitForElement('#configForm', 10);

        // Fill description
        I.clearField('#configDescription');
        I.fillField(
            '#configDescription',
            'News-maker agent для AI Community Platform. Парсить, ранжує та переписує AI-новини українською.',
        );

        // Fill system prompt
        I.clearField('#configSystemPrompt');
        I.fillField(
            '#configSystemPrompt',
            'Ти — AI-агент новинного дайджесту для української технічної спільноти. '
                + 'Відбирай лише якісні, актуальні новини про штучний інтелект. '
                + 'Ігноруй маркетингові матеріали та клікбейт. '
                + 'Переписуй новини зрозумілою українською мовою.',
        );

        // Save config
        I.click('#configSaveBtn');
        await I.waitForText('Збережено', 10, '#configResult');
    },
).tag('@news-digest').tag('@admin');

Scenario(
    'step 2: add TechCrunch AI source',
    async ({ I }) => {
        // Use the iframe inside the agent settings page
        I.amOnPage(`/admin/agents/${AGENT_NAME}/settings`);
        await I.waitForElement('iframe', 10);

        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 10);

        // Check if TechCrunch source already exists
        const existingCount = await I.grabNumberOfVisibleElements(
            `//tr[contains(.,"${SOURCE_NAME}")]`,
        );

        if (existingCount === 0) {
            // Open the add-source modal
            I.click('+ Додати джерело');
            await I.waitForElement('#addModal.show', 5);

            // Fill form — use Playwright fill() directly for the URL field
            // because type="url" fields can have browser validation issues with fillField
            I.fillField('name', SOURCE_NAME);

            // Clear and fill URL field using Playwright API for reliability
            I.usePlaywrightTo('fill URL field', async ({ page }) => {
                const frame = page.frames()[1]; // iframe is the second frame
                const urlInput = frame.locator('input[name="base_url"]');
                await urlInput.fill(TECHCRUNCH_URL);
            });

            // topic_scope already has default "ai", just clear and re-fill for safety
            I.usePlaywrightTo('fill topic and priority', async ({ page }) => {
                const frame = page.frames()[1];
                await frame.locator('input[name="topic_scope"]').fill('ai');
                await frame.locator('input[name="crawl_priority"]').fill('9');
            });

            // Submit — form POSTs and iframe redirects (303)
            I.click('Зберегти');

            // After iframe redirect, re-enter iframe context
            await I.switchTo();
            await I.wait(2);
            await I.switchTo('iframe');
            await I.waitForText('Джерела новин', 10);
        }

        // Verify source appears in table (use XPath for reliable match)
        I.seeElement(`//td[contains(text(),"${SOURCE_NAME}")]`);
        I.see('Активне');

        await I.switchTo();
    },
).tag('@news-digest').tag('@admin');

Scenario(
    'step 3: trigger crawl and wait for completion',
    async ({ I }) => {
        // First, count existing completed runs (from previous cron runs)
        I.amOnPage(`/admin/agents/${AGENT_NAME}/settings`);
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        I.click('Налаштування');
        await I.waitForText('Останні запуски планувальника', 10);
        const baselineCompleted = await I.grabNumberOfVisibleElements(
            '//span[contains(@class,"bg-success") and contains(text(),"completed")]',
        );
        await I.switchTo();

        // Trigger crawl from core admin button
        I.amOnPage(`/admin/agents/${AGENT_NAME}/settings`);
        await I.waitForElement('#crawlTriggerBtn', 10);
        I.click('#crawlTriggerBtn');
        await I.waitForText('Парсинг запущено', 20, '#crawlTriggerResult');

        // Poll for a NEW completed run (more than baseline).
        // Real-site crawl + LLM ranking + rewriting can take 30-120s.
        let found = false;
        for (let attempt = 0; attempt < 30; attempt++) {
            await I.wait(5);

            I.amOnPage(`/admin/agents/${AGENT_NAME}/settings`);
            await I.waitForElement('iframe', 10);
            await I.switchTo('iframe');

            I.click('Налаштування');
            await I.waitForText('Останні запуски планувальника', 10);

            const completedCount = await I.grabNumberOfVisibleElements(
                '//span[contains(@class,"bg-success") and contains(text(),"completed")]',
            );
            await I.switchTo();

            if (completedCount > baselineCompleted) {
                found = true;
                break;
            }

            // Also check for failure
            await I.switchTo('iframe');
            const failedCount = await I.grabNumberOfVisibleElements(
                '//span[contains(@class,"bg-danger") and contains(text(),"failed")]',
            );
            await I.switchTo();
            // Don't fail on failed count — there may be old failures
        }

        assert.ok(found, 'Crawl pipeline did not produce a new completed run within 150 seconds');
    },
).tag('@news-digest').tag('@admin');

Scenario(
    'step 4: verify curated news items and paginator',
    async ({ I }) => {
        // Navigate to curated news page via iframe
        I.amOnPage(`/admin/agents/${AGENT_NAME}/settings`);
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');

        // Click "Кюровані" nav link to go to curated news page
        I.click('Кюровані');
        await I.waitForText('Кюровані новини', 15);

        // Verify we have items (not the empty state)
        I.dontSee('Кюрованих новин ще немає');

        // Verify total count is shown
        I.seeElement('//span[contains(@class,"text-muted") and contains(text(),"Всього:")]');

        // Verify items exist in the table
        const itemCount = await I.grabNumberOfVisibleElements('table tbody tr');
        assert.ok(itemCount > 0, `Expected curated items in table, got ${itemCount}`);

        // Verify status badges are present (items should be in 'ready' status after crawl)
        I.seeElement('//span[contains(@class,"badge")]');

        // Verify status filter buttons exist (normalize whitespace)
        I.seeElement('//a[contains(@class,"btn") and normalize-space()="Всі"]');
        I.seeElement('//a[contains(@class,"btn") and normalize-space()="ready"]');

        // Check paginator — with few items from a single crawl, should show only 1 page
        // The pagination nav is rendered only when total_pages > 1
        const paginatorCount = await I.grabNumberOfVisibleElements('nav .pagination');
        if (paginatorCount > 0) {
            // Paginator visible — verify page 1 is active
            I.seeElement('//li[contains(@class,"active")]//a[text()="1"]');
        }

        // Log results
        console.log(`\n  Curated news items on page: ${itemCount}`);
        console.log(`  Paginator: ${paginatorCount > 0 ? 'visible' : 'hidden (all fit on 1 page)'}`);

        await I.switchTo();
    },
).tag('@news-digest').tag('@admin');
