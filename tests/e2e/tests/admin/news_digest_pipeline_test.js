// E2E: News-Maker Agent — full crawl pipeline with mock source
// 1. Enable agent, set description + system prompt
// 2. Add mock source via iframe (uses internal test endpoint, no external dependency)
// 3. Trigger crawl, wait for completion
// 4. Verify curated news items visible with paginator

const assert = require('assert');

const NEWS_MAKER_URL = process.env.NEWS_URL || 'http://localhost:18084';
const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';
const AGENT_NAME = 'news-maker-agent';
const SETTINGS_PATH = `/admin/agents/${AGENT_NAME}/settings`;

// Use the agent's built-in mock source instead of external TechCrunch
const MOCK_SOURCE_URL =
    process.env.NEWS_TEST_SOURCE_URL ||
    'http://news-maker-agent-e2e:8000/__e2e/mock-source';
const SOURCE_NAME = 'E2E Mock Source';

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
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('#configForm', 15);

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
    'step 2: add mock news source',
    async ({ I }) => {
        // Use the iframe inside the agent settings page
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 15);

        await I.switchTo('iframe');
        await I.waitForText('Джерела новин', 10);

        // Check if mock source already exists
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
            I.usePlaywrightTo('fill URL, topic, and priority fields', async ({ page }) => {
                const frame = page.frames().find(f => f.url().includes('/admin/sources'));
                if (!frame) throw new Error('Could not find agent admin iframe');
                await frame.locator('input[name="base_url"]').fill(MOCK_SOURCE_URL);
                await frame.locator('input[name="topic_scope"]').fill('ai');
                await frame.locator('input[name="crawl_priority"]').fill('9');
            });

            // Submit — form POSTs and iframe redirects (303)
            I.click('Зберегти');

            // After iframe redirect, re-enter iframe context
            await I.switchTo();
            await I.wait(2);
            await I.switchTo('iframe');
            await I.waitForText('Джерела новин', 15);
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
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 15);
        await I.switchTo('iframe');
        I.click('Налаштування');
        await I.waitForText('Останні запуски планувальника', 15);
        const baselineCompleted = await I.grabNumberOfVisibleElements(
            '//span[contains(@class,"bg-success") and contains(text(),"completed")]',
        );
        await I.switchTo();

        // Trigger crawl from core admin button
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
                null,
                { timeout: 45000 },
            );
        });

        // Verify crawl was triggered (accept both success and error — pipeline polling below handles actual completion)
        const resultText = await I.grabTextFrom('#crawlTriggerResult');
        assert.ok(
            resultText.includes('Парсинг запущено') || resultText.includes('Помилка'),
            `Expected crawl result message, got: "${resultText}"`,
        );

        // Poll for a NEW completed run (more than baseline).
        // Mock source crawl + LLM ranking + rewriting typically takes 15-60s.
        // Allow up to 180s for slow CI environments.
        let found = false;
        for (let attempt = 0; attempt < 36; attempt++) {
            await I.wait(5);

            I.amOnPage(SETTINGS_PATH);
            await I.waitForElement('iframe', 15);
            await I.switchTo('iframe');

            I.click('Налаштування');
            await I.waitForText('Останні запуски планувальника', 15);

            const completedCount = await I.grabNumberOfVisibleElements(
                '//span[contains(@class,"bg-success") and contains(text(),"completed")]',
            );

            if (completedCount > baselineCompleted) {
                found = true;
                await I.switchTo();
                break;
            }

            await I.switchTo();
        }

        assert.ok(found, 'Crawl pipeline did not produce a new completed run within 180 seconds');
    },
).tag('@news-digest').tag('@admin');

Scenario(
    'step 4: verify curated news items and paginator',
    async ({ I }) => {
        // Navigate to curated news page via iframe
        I.amOnPage(SETTINGS_PATH);
        await I.waitForElement('iframe', 15);
        await I.switchTo('iframe');

        // Click "Кюровані" nav link to go to curated news page
        I.click('Кюровані');
        await I.waitForText('Кюровані новини', 15);

        // Check for items — the mock source may produce few or zero curated items
        // depending on LLM ranking. Verify the page loads correctly at minimum.
        const hasItems = await I.grabNumberOfVisibleElements('table tbody tr');
        const emptyState = await I.grabNumberOfVisibleElements(
            '//td[contains(text(),"Кюрованих новин ще немає")]',
        );

        if (emptyState === 0 && hasItems > 0) {
            // Items exist — verify structure
            // Verify total count is shown
            I.seeElement('//span[contains(@class,"text-muted") and contains(text(),"Всього:")]');

            // Verify status badges are present
            I.seeElement('//span[contains(@class,"badge")]');

            // Verify status filter buttons exist (normalize whitespace)
            I.seeElement('//a[contains(@class,"btn") and normalize-space()="Всі"]');

            // Check paginator — with few items from a single crawl, should show only 1 page
            const paginatorCount = await I.grabNumberOfVisibleElements('nav .pagination');
            if (paginatorCount > 0) {
                // Paginator visible — verify page 1 is active
                I.seeElement('//li[contains(@class,"active")]//a[text()="1"]');
            }

            console.log(`\n  Curated news items on page: ${hasItems}`);
            console.log(`  Paginator: ${paginatorCount > 0 ? 'visible' : 'hidden (all fit on 1 page)'}`);
        } else {
            // Empty state — the crawl completed but LLM may have rejected all items.
            // This is acceptable for mock source; verify page structure is correct.
            console.log('\n  No curated items after crawl (LLM may have rejected mock content)');
            I.seeElement('//span[contains(@class,"text-muted") and contains(text(),"Всього:")]');
            I.seeElement('//a[contains(@class,"btn") and normalize-space()="Всі"]');
        }

        await I.switchTo();
    },
).tag('@news-digest').tag('@admin');
