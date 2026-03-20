// E2E: Knowledge Agent Admin CRUD Operations
// Tests knowledge entry CRUD operations and instruction preview functionality.

const KNOWLEDGE_ADMIN_URL = process.env.KNOWLEDGE_URL
    ? `${process.env.KNOWLEDGE_URL}/admin/knowledge`
    : 'http://localhost:18083/admin/knowledge';
const INTERNAL_TOKEN = process.env.APP_INTERNAL_TOKEN || 'dev-internal-token';

Feature('Admin: Knowledge Agent CRUD Operations');

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();

    // Ensure the agent registry has the correct E2E admin_url
    await I.sendPostRequest(
        '/api/v1/internal/agents/register',
        JSON.stringify({
            name: 'knowledge-agent',
            version: '1.0.0',
            description: 'Knowledge base management and semantic search',
            url: 'http://knowledge-agent-e2e/api/v1/knowledge/a2a',
            admin_url: KNOWLEDGE_ADMIN_URL,
            skills: [
                { id: 'knowledge.search', name: 'Knowledge Search', description: 'Search the knowledge base' },
                { id: 'knowledge.upload', name: 'Knowledge Upload', description: 'Extract and store knowledge' },
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
    'can create new knowledge entry via admin panel',
    async ({ I }) => {
        I.amOnPage('/admin/agents/knowledge-agent/settings');
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForElement('.tabs', 10);

        // Navigate to Entries tab (should be default)
        I.click('Записи');
        await I.waitForElement('.create-entry-btn', 5);

        // Click create new entry button
        I.click('.create-entry-btn');
        await I.waitForElement('#entryForm', 5);

        // Fill in entry details
        I.fillField('#title', 'E2E Test Entry');
        I.fillField('#body', 'This is a test knowledge entry created via E2E testing.');
        I.fillField('#category', 'Testing');
        I.fillField('#tree_path', 'Testing/E2E');
        I.fillField('#tags', 'e2e, testing, automation');

        // Save the entry
        I.click('#saveEntry');
        await I.waitForText('Запис збережено', 5);

        // Verify entry appears in the list
        I.see('E2E Test Entry');
        I.see('Testing/E2E');
        I.see('e2e, testing, automation');

        await I.switchTo();
    },
).tag('@admin').tag('@knowledge').tag('@crud');

Scenario(
    'can edit existing knowledge entry',
    async ({ I }) => {
        // First create an entry to edit
        const KNOWLEDGE_URL = process.env.KNOWLEDGE_URL || 'http://localhost:18083';
        await I.sendPostRequest(
            `${KNOWLEDGE_URL}/api/v1/knowledge/entries`,
            JSON.stringify({
                title: 'Entry to Edit',
                body: 'Original content',
                category: 'Testing',
                tree_path: 'Testing/Edit',
                tags: ['edit', 'test'],
                source_message_id: 'edit_test_msg',
                message_link: 'https://t.me/test_chat/edit',
                created_by: 'e2e_test',
                created_at: new Date().toISOString(),
            }),
            { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
        );

        I.amOnPage('/admin/agents/knowledge-agent/settings');
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForElement('.tabs', 10);

        // Navigate to Entries tab
        I.click('Записи');
        await I.waitForText('Entry to Edit', 5);

        // Click edit button for the entry
        I.click('.edit-btn[data-title="Entry to Edit"]');
        await I.waitForElement('#entryForm', 5);

        // Verify form is populated with existing data
        I.seeInField('#title', 'Entry to Edit');
        I.seeInField('#body', 'Original content');

        // Edit the entry
        I.clearField('#title');
        I.fillField('#title', 'Edited Entry Title');
        I.clearField('#body');
        I.fillField('#body', 'Updated content with new information.');
        I.clearField('#tags');
        I.fillField('#tags', 'edited, updated, test');

        // Save changes
        I.click('#saveEntry');
        await I.waitForText('Запис оновлено', 5);

        // Verify changes are reflected in the list
        I.see('Edited Entry Title');
        I.see('edited, updated, test');
        I.dontSee('Entry to Edit');

        await I.switchTo();
    },
).tag('@admin').tag('@knowledge').tag('@crud');

Scenario(
    'can delete knowledge entry with confirmation',
    async ({ I }) => {
        // First create an entry to delete
        const KNOWLEDGE_URL = process.env.KNOWLEDGE_URL || 'http://localhost:18083';
        await I.sendPostRequest(
            `${KNOWLEDGE_URL}/api/v1/knowledge/entries`,
            JSON.stringify({
                title: 'Entry to Delete',
                body: 'This entry will be deleted',
                category: 'Testing',
                tree_path: 'Testing/Delete',
                tags: ['delete', 'test'],
                source_message_id: 'delete_test_msg',
                message_link: 'https://t.me/test_chat/delete',
                created_by: 'e2e_test',
                created_at: new Date().toISOString(),
            }),
            { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
        );

        I.amOnPage('/admin/agents/knowledge-agent/settings');
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForElement('.tabs', 10);

        // Navigate to Entries tab
        I.click('Записи');
        await I.waitForText('Entry to Delete', 5);

        // Click delete button for the entry
        I.click('.delete-btn[data-title="Entry to Delete"]');
        
        // Confirmation dialog should appear (auto-accepted by Before hook)
        await I.waitForText('Запис видалено', 5);

        // Verify entry is removed from the list
        I.dontSee('Entry to Delete');

        await I.switchTo();
    },
).tag('@admin').tag('@knowledge').tag('@crud');

Scenario(
    'can filter and search entries in admin panel',
    async ({ I }) => {
        // Create multiple test entries
        const KNOWLEDGE_URL = process.env.KNOWLEDGE_URL || 'http://localhost:18083';
        const testEntries = [
            {
                title: 'JavaScript Basics',
                body: 'Introduction to JavaScript programming',
                category: 'Programming',
                tree_path: 'Programming/JavaScript',
                tags: ['javascript', 'basics'],
                source_message_id: 'js_msg',
                message_link: 'https://t.me/test_chat/js',
                created_by: 'e2e_test',
                created_at: new Date().toISOString(),
            },
            {
                title: 'Python Advanced',
                body: 'Advanced Python concepts and patterns',
                category: 'Programming',
                tree_path: 'Programming/Python',
                tags: ['python', 'advanced'],
                source_message_id: 'py_msg',
                message_link: 'https://t.me/test_chat/py',
                created_by: 'e2e_test',
                created_at: new Date().toISOString(),
            },
        ];

        for (const entry of testEntries) {
            await I.sendPostRequest(
                `${KNOWLEDGE_URL}/api/v1/knowledge/entries`,
                JSON.stringify(entry),
                { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
            );
        }

        I.amOnPage('/admin/agents/knowledge-agent/settings');
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForElement('.tabs', 10);

        // Navigate to Entries tab
        I.click('Записи');
        await I.waitForText('JavaScript Basics', 5);

        // Test search functionality
        I.fillField('.search-entries', 'JavaScript');
        await I.waitForText('JavaScript Basics', 3);
        I.see('JavaScript Basics');
        I.dontSee('Python Advanced');

        // Clear search and test category filter
        I.clearField('.search-entries');
        I.selectOption('.filter-category', 'Programming');
        await I.waitForText('JavaScript Basics', 3);
        I.see('JavaScript Basics');
        I.see('Python Advanced');

        await I.switchTo();
    },
).tag('@admin').tag('@knowledge').tag('@crud');

Scenario(
    'instruction preview functionality works',
    async ({ I }) => {
        I.amOnPage('/admin/agents/knowledge-agent/settings');
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForElement('.tabs', 10);

        // Navigate to Preview tab
        I.click('Перевірка');
        await I.waitForElement('#previewInput', 5);

        // Enter test input for preview
        I.fillField('#previewInput', 'This is a test message about PHP unit testing with PHPUnit framework.');

        // Click preview button
        I.click('#runPreview');
        await I.waitForElement('.preview-result', 10);

        // Check that preview result is displayed
        I.seeElement('.preview-result');
        I.see('Результат попереднього перегляду');

        // The result should contain analysis or extraction preview
        // (exact content depends on the workflow implementation)
        I.seeElement('.analysis-result');
        I.seeElement('.extraction-result');

        await I.switchTo();
    },
).tag('@admin').tag('@knowledge').tag('@preview');

Scenario(
    'DLQ monitor shows dead letter queue status',
    async ({ I }) => {
        I.amOnPage('/admin/agents/knowledge-agent/settings');
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForElement('.tabs', 10);

        // Navigate to DLQ Monitor tab
        I.click('DLQ Монітор');
        await I.waitForElement('.dlq-status', 5);

        // Check DLQ status is displayed
        I.see('Стан черги помилок');
        I.seeElement('.dlq-count');
        I.seeElement('.requeue-btn');

        // DLQ count should be a number (0 or more)
        I.see(/\d+/);

        await I.switchTo();
    },
).tag('@admin').tag('@knowledge').tag('@dlq');

Scenario(
    'pagination works for large entry lists',
    async ({ I }) => {
        // Create many test entries to test pagination
        const KNOWLEDGE_URL = process.env.KNOWLEDGE_URL || 'http://localhost:18083';
        const entries = [];
        for (let i = 1; i <= 25; i++) {
            entries.push({
                title: `Test Entry ${i.toString().padStart(2, '0')}`,
                body: `Content for test entry number ${i}`,
                category: 'Testing',
                tree_path: 'Testing/Pagination',
                tags: ['test', 'pagination'],
                source_message_id: `pagination_msg_${i}`,
                message_link: `https://t.me/test_chat/pagination_${i}`,
                created_by: 'e2e_test',
                created_at: new Date().toISOString(),
            });
        }

        // Create entries in batches to avoid overwhelming the API
        for (let i = 0; i < entries.length; i += 5) {
            const batch = entries.slice(i, i + 5);
            for (const entry of batch) {
                await I.sendPostRequest(
                    `${KNOWLEDGE_URL}/api/v1/knowledge/entries`,
                    JSON.stringify(entry),
                    { 'Content-Type': 'application/json', 'X-Platform-Internal-Token': INTERNAL_TOKEN },
                );
            }
        }

        I.amOnPage('/admin/agents/knowledge-agent/settings');
        await I.waitForElement('iframe', 10);
        await I.switchTo('iframe');
        await I.waitForElement('.tabs', 10);

        // Navigate to Entries tab
        I.click('Записи');
        await I.waitForText('Test Entry 01', 5);

        // Check pagination controls are visible
        I.seeElement('.pagination');
        I.seeElement('.page-next');

        // Navigate to next page
        I.click('.page-next');
        await I.waitForText('Test Entry', 5);

        // Should see different entries on page 2
        I.see('Test Entry');

        await I.switchTo();
    },
).tag('@admin').tag('@knowledge').tag('@crud').tag('@pagination');