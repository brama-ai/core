/** @type {CodeceptJS.MainConfig} */
exports.config = {
    tests: './tests/**/*_test.js',
    output: './output',
    helpers: {
        Playwright: {
            url: process.env.BASE_URL || 'http://localhost',
            show: process.env.HEADLESS === 'false',
            browser: 'chromium',
            waitForNavigation: 'networkidle',
            waitForTimeout: 10000,
        },
        REST: {
            endpoint: process.env.BASE_URL || 'http://localhost',
            defaultHeaders: {
                Accept: 'application/json',
            },
            timeout: 10000,
        },
        JSONResponse: {},
    },
    include: {
        I: './support/steps_file.js',
        loginPage: './support/pages/LoginPage.js',
        agentsPage: './support/pages/AgentsPage.js',
        logsPage: './support/pages/LogsPage.js',
        chatsPage: './support/pages/ChatsPage.js',
    },
    name: 'ai-community-platform-e2e',
};
