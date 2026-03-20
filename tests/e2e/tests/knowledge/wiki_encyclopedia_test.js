// E2E: Knowledge Agent Web Encyclopedia
// Tests tree navigation, search functionality, and source link visibility in the public wiki.

const KNOWLEDGE_URL = process.env.KNOWLEDGE_URL || 'http://localhost:18083';
const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';

Feature('Knowledge Agent: Web Encyclopedia');

Before(async ({ I }) => {
    // Ensure encyclopedia is enabled
    await I.sendPostRequest(
        `${KNOWLEDGE_URL}/api/v1/internal/settings`,
        JSON.stringify({
            encyclopedia_enabled: true,
        }),
        { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
    );

    // Seed some test knowledge entries for testing
    const testEntries = [
        {
            title: 'PHP Testing Best Practices',
            body: 'Unit tests should be fast, isolated, and deterministic. Use PHPUnit for testing PHP applications.',
            category: 'Development',
            tree_path: 'Development/PHP/Testing',
            tags: ['php', 'testing', 'phpunit'],
            source_message_id: 'test_msg_1',
            message_link: 'https://t.me/test_chat/1',
            created_by: 'test_user',
            created_at: new Date().toISOString(),
        },
        {
            title: 'Symfony Framework Overview',
            body: 'Symfony is a PHP framework for web applications. It provides reusable components and follows MVC architecture.',
            category: 'Development',
            tree_path: 'Development/PHP/Frameworks',
            tags: ['php', 'symfony', 'framework'],
            source_message_id: 'test_msg_2',
            message_link: 'https://t.me/test_chat/2',
            created_by: 'test_user',
            created_at: new Date().toISOString(),
        },
        {
            title: 'Docker Containerization',
            body: 'Docker allows you to package applications into containers for consistent deployment across environments.',
            category: 'DevOps',
            tree_path: 'DevOps/Containerization',
            tags: ['docker', 'containers', 'devops'],
            source_message_id: 'test_msg_3',
            message_link: 'https://t.me/test_chat/3',
            created_by: 'test_user',
            created_at: new Date().toISOString(),
        },
    ];

    // Create test entries
    for (const entry of testEntries) {
        await I.sendPostRequest(
            `${KNOWLEDGE_URL}/api/v1/knowledge/entries`,
            JSON.stringify(entry),
            { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
        );
    }
});

After(async ({ I }) => {
    // Clean up test entries
    try {
        const response = await I.sendGetRequest(`${KNOWLEDGE_URL}/api/v1/knowledge/entries?size=100`);
        const entries = response.data.entries || [];
        
        for (const entry of entries) {
            if (entry.created_by === 'test_user') {
                await I.sendDeleteRequest(
                    `${KNOWLEDGE_URL}/api/v1/knowledge/entries/${entry.id}`,
                    { 'X-Platform-Internal-Token': INTERNAL_TOKEN }
                );
            }
        }
    } catch (error) {
        console.log('Cleanup error:', error.message);
    }
});

Scenario(
    'wiki homepage loads with tree navigation',
    async ({ I }) => {
        await I.ensureEdgeAccess(`${KNOWLEDGE_URL}/wiki`);
        I.amOnPage(`${KNOWLEDGE_URL}/wiki`);
        
        // Check main layout elements
        I.seeElement('.tree-sidebar');
        I.seeElement('.content-area');
        I.seeElement('.search-bar');
        
        // Check tree structure is visible
        I.see('Development');
        I.see('DevOps');
        
        // Check welcome message or default content
        I.see('Ласкаво просимо');
    },
).tag('@knowledge').tag('@wiki');

Scenario(
    'can navigate tree structure and view entries',
    async ({ I }) => {
        await I.ensureEdgeAccess(`${KNOWLEDGE_URL}/wiki`);
        I.amOnPage(`${KNOWLEDGE_URL}/wiki`);
        
        // Expand Development category
        I.click('Development');
        await I.waitForElement('[data-tree-path*="Development/PHP"]', 5);
        
        // Navigate to PHP subcategory
        I.click('PHP');
        await I.waitForElement('[data-tree-path="Development/PHP/Testing"]', 5);
        
        // Click on Testing subcategory to see entries
        I.click('Testing');
        await I.waitForText('PHP Testing Best Practices', 5);
        
        // Click on the entry to view details
        I.click('PHP Testing Best Practices');
        await I.waitForText('Unit tests should be fast', 5);
        
        // Verify entry content is displayed
        I.see('PHP Testing Best Practices');
        I.see('Unit tests should be fast, isolated, and deterministic');
        I.see('Development/PHP/Testing');
        I.see('php');
        I.see('testing');
        I.see('phpunit');
    },
).tag('@knowledge').tag('@wiki');

Scenario(
    'search functionality works correctly',
    async ({ I }) => {
        await I.ensureEdgeAccess(`${KNOWLEDGE_URL}/wiki`);
        I.amOnPage(`${KNOWLEDGE_URL}/wiki`);
        
        // Test keyword search
        I.fillField('.search-bar input', 'PHP');
        I.pressKey('Enter');
        await I.waitForText('PHP Testing Best Practices', 5);
        I.see('Symfony Framework Overview');
        
        // Test more specific search
        I.clearField('.search-bar input');
        I.fillField('.search-bar input', 'Docker containers');
        I.pressKey('Enter');
        await I.waitForText('Docker Containerization', 5);
        I.see('package applications into containers');
        
        // Test search with no results
        I.clearField('.search-bar input');
        I.fillField('.search-bar input', 'nonexistent topic xyz');
        I.pressKey('Enter');
        await I.waitForText('Результатів не знайдено', 5);
    },
).tag('@knowledge').tag('@wiki');

Scenario(
    'source message links are visible and functional',
    async ({ I }) => {
        await I.ensureEdgeAccess(`${KNOWLEDGE_URL}/wiki`);
        I.amOnPage(`${KNOWLEDGE_URL}/wiki`);
        
        // Navigate to an entry
        I.fillField('.search-bar input', 'PHP Testing');
        I.pressKey('Enter');
        await I.waitForText('PHP Testing Best Practices', 5);
        I.click('PHP Testing Best Practices');
        
        // Check that source link is visible
        await I.waitForElement('.source-link', 5);
        I.see('Перейти до джерела');
        
        // Verify the link has correct attributes
        I.seeElement('a[href*="t.me/test_chat/1"]');
        I.seeElement('a[target="_blank"]');
        
        // Check metadata is displayed
        I.see('test_user');
        I.see('Development');
    },
).tag('@knowledge').tag('@wiki');

Scenario(
    'encyclopedia respects disabled state',
    async ({ I }) => {
        // Disable encyclopedia
        await I.sendPostRequest(
            `${KNOWLEDGE_URL}/api/v1/internal/settings`,
            JSON.stringify({
                encyclopedia_enabled: false,
            }),
            { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
        );
        
        await I.ensureEdgeAccess(`${KNOWLEDGE_URL}/wiki`);
        I.amOnPage(`${KNOWLEDGE_URL}/wiki`);
        
        // Should see 503 service unavailable message
        I.see('недоступна');
        I.dontSeeElement('.tree-sidebar');
        I.dontSeeElement('.search-bar');
        
        // Re-enable for cleanup
        await I.sendPostRequest(
            `${KNOWLEDGE_URL}/api/v1/internal/settings`,
            JSON.stringify({
                encyclopedia_enabled: true,
            }),
            { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
        );
    },
).tag('@knowledge').tag('@wiki');

Scenario(
    'tree navigation shows entry counts',
    async ({ I }) => {
        await I.ensureEdgeAccess(`${KNOWLEDGE_URL}/wiki`);
        I.amOnPage(`${KNOWLEDGE_URL}/wiki`);
        
        // Check that categories show entry counts
        I.see('Development (2)'); // Should have 2 PHP entries
        I.see('DevOps (1)');      // Should have 1 Docker entry
        
        // Expand Development to see subcategories with counts
        I.click('Development');
        await I.waitForElement('[data-tree-path*="Development/PHP"]', 5);
        I.see('PHP (2)');
    },
).tag('@knowledge').tag('@wiki');

Scenario(
    'responsive design works on mobile viewport',
    async ({ I }) => {
        // Set mobile viewport
        I.usePlaywrightTo('set mobile viewport', async ({ page }) => {
            await page.setViewportSize({ width: 375, height: 667 });
        });
        
        await I.ensureEdgeAccess(`${KNOWLEDGE_URL}/wiki`);
        I.amOnPage(`${KNOWLEDGE_URL}/wiki`);
        
        // On mobile, tree sidebar should be collapsible or hidden
        I.seeElement('.mobile-menu-toggle');
        
        // Search should still be accessible
        I.seeElement('.search-bar');
        
        // Content area should be full width on mobile
        I.seeElement('.content-area');
        
        // Reset viewport
        I.usePlaywrightTo('reset viewport', async ({ page }) => {
            await page.setViewportSize({ width: 1280, height: 720 });
        });
    },
).tag('@knowledge').tag('@wiki').tag('@mobile');