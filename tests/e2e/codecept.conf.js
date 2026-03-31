/** @type {CodeceptJS.MainConfig} */
exports.config = {
    tests: './tests/**/*_test.js',
    output: './output',
    helpers: {
        Playwright: {
            url: process.env.BASE_URL || 'http://localhost:18080',
            show: process.env.HEADLESS === 'false',
            browser: 'chromium',
            waitForNavigation: 'load',
            waitForTimeout: 30000,
            restart: false,
        },
        REST: {
            endpoint: process.env.BASE_URL || 'http://localhost:18080',
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
        schedulerPage: './support/pages/SchedulerPage.js',
        tenantsPage: './support/pages/TenantsPage.js',
        dashboardPage: './support/pages/DashboardPage.js',
        localePage: './support/pages/LocalePage.js',
        settingsPage: './support/pages/SettingsPage.js',
        coderPage: './support/pages/CoderPage.js',
        logTracePage: './support/pages/LogTracePage.js',
        agentSettingsPage: './support/pages/AgentSettingsPage.js',
        devReporterPage: './support/pages/DevReporterPage.js',
    },
    name: 'ai-community-platform-e2e',
};
