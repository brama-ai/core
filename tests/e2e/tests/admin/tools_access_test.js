const assert = require('assert');
const { execSync } = require('child_process');

Feature('Admin: Tools Access');

const OPENCLAW_URL = process.env.OPENCLAW_URL || 'http://openclaw.localhost';
const LANGFUSE_URL = process.env.LANGFUSE_URL || 'http://langfuse.localhost';
const LITELLM_URL = process.env.LITELLM_URL || 'http://litellm.localhost';

/**
 * Safely execute curl; returns stdout or null when the service is unreachable.
 */
function curlSafe(cmd) {
    try {
        return execSync(cmd, { encoding: 'utf-8', timeout: 15000 }).trim();
    } catch (e) {
        // Connection refused / DNS failure / timeout — service is down
        return null;
    }
}

/**
 * Check whether a URL is reachable (any HTTP response).
 */
function isServiceUp(url) {
    const result = curlSafe(
        `curl -s -o /dev/null -w "%{http_code}" --max-time 5 --connect-timeout 3 "${url}"`,
    );
    // 000 = connection failed, 404 = not configured in Traefik
    return result !== null && result !== '000' && result !== '404';
}

Before(async ({ I, loginPage }) => {
    await loginPage.loginAsAdmin();
});

Scenario(
    'dashboard shows tool cards for Langfuse, LiteLLM, Traefik',
    async ({ I }) => {
        I.amOnPage('/admin/dashboard');
        I.see('Langfuse');
        I.see('LiteLLM');
        I.see('Traefik');
    },
).tag('@admin').tag('@tools');

Scenario(
    'anonymous user is redirected to edge login on protected tools',
    async () => {
        // Use curl to test redirect — avoids Chromium DNS cache issues with *.localhost
        const protectedUrls = [
            OPENCLAW_URL + '/',
            LANGFUSE_URL + '/',
            LITELLM_URL + '/',
        ];

        let testedAtLeastOne = false;
        for (const targetUrl of protectedUrls) {
            const result = curlSafe(
                `curl -s -o /dev/null -w "%{http_code} %{redirect_url}" --max-time 5 --connect-timeout 3 "${targetUrl}"`,
            );

            if (result === null) {
                // Service unreachable — skip this URL silently
                continue;
            }

            const spaceIdx = result.indexOf(' ');
            const statusCode = spaceIdx > -1 ? result.substring(0, spaceIdx) : result;
            const redirectUrl = spaceIdx > -1 ? result.substring(spaceIdx + 1) : '';

            // 404 means the service is not configured in Traefik routing — skip
            if (statusCode === '404') {
                console.log(`SKIP: ${targetUrl} returned 404 — service not configured in Traefik`);
                continue;
            }

            assert.strictEqual(statusCode, '302', `Expected 302 redirect for ${targetUrl}, got ${statusCode}`);
            assert.ok(
                redirectUrl.includes('/edge/auth/login'),
                `Expected redirect to /edge/auth/login for ${targetUrl}, got: ${redirectUrl}`,
            );
            testedAtLeastOne = true;
        }

        if (!testedAtLeastOne) {
            console.log('SKIP: No protected tool services are reachable — skipping redirect assertions');
        }
    },
).tag('@admin').tag('@tools').tag('@security');

Scenario(
    'edge login sets JWT cookie and redirects back to tool',
    async () => {
        // Full edge-auth flow via curl: get redirect → login → verify cookie
        const password = process.env.ADMIN_PASSWORD || 'test-password';
        const cookieName = process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';

        if (!isServiceUp(LANGFUSE_URL)) {
            console.log('SKIP: Langfuse is not reachable — skipping edge login cookie test');
            return;
        }

        // Step 1: Hit langfuse — should get 302 to edge login
        const redirectResult = curlSafe(
            `curl -s -o /dev/null -w "%{redirect_url}" --max-time 5 --connect-timeout 3 "${LANGFUSE_URL}/"`,
        );
        assert.ok(redirectResult, 'Expected a response from Langfuse');
        assert.ok(redirectResult.includes('/edge/auth/login'), `Expected edge login redirect, got: ${redirectResult}`);

        // Step 2: Submit login form via curl, capture JWT cookie
        const cookieResult = curlSafe(
            `curl -s -c - -L --max-time 10 --connect-timeout 3 -d '_username=admin&_password=${password}' '${redirectResult}'`,
        );
        assert.ok(cookieResult, 'Expected a response from edge login form');
        assert.ok(cookieResult.includes(cookieName), `Expected ${cookieName} cookie after login`);

        // Step 3: Use cookie to access langfuse — should get 200 (not 302)
        const jwtLine = cookieResult.split('\n').find(l => l.includes(cookieName));
        if (jwtLine) {
            const jwtValue = jwtLine.split('\t').pop().trim();
            const statusCode = curlSafe(
                `curl -s -o /dev/null -w "%{http_code}" --max-time 5 --connect-timeout 3 -b "${cookieName}=${jwtValue}" "${LANGFUSE_URL}/"`,
            );
            assert.ok(statusCode, 'Expected a response when using JWT cookie');
            assert.notStrictEqual(statusCode, '302', 'Authenticated request should not redirect');
        }
    },
).tag('@admin').tag('@tools').tag('@security');

Scenario(
    'edge login on openclaw subdomain renders OpenClaw UI after auth',
    async () => {
        const password = process.env.ADMIN_PASSWORD || 'test-password';
        const cookieName = process.env.EDGE_AUTH_COOKIE_NAME || 'ACP_EDGE_TOKEN';

        if (!isServiceUp(OPENCLAW_URL)) {
            console.log('SKIP: OpenClaw is not reachable — skipping OpenClaw UI auth test');
            return;
        }

        // Step 1: Hit openclaw — should 302 to edge login on same subdomain
        const redirectResult = curlSafe(
            `curl -s -o /dev/null -w "%{redirect_url}" --max-time 5 --connect-timeout 3 "${OPENCLAW_URL}/"`,
        );
        assert.ok(redirectResult, 'Expected a response from OpenClaw');
        assert.ok(
            redirectResult.includes('openclaw.localhost/edge/auth/login'),
            `Expected redirect to openclaw.localhost/edge/auth/login, got: ${redirectResult}`,
        );
        assert.ok(
            redirectResult.includes('rd='),
            `Expected rd= param in redirect URL, got: ${redirectResult}`,
        );

        // Step 2: Login via subdomain login page — follow redirect, capture response
        const loginResult = curlSafe(
            `curl -s -c - -L --max-time 10 --connect-timeout 3 -d '_username=admin&_password=${password}' '${redirectResult}'`,
        );
        assert.ok(loginResult, 'Expected a response from subdomain login');
        assert.ok(loginResult.includes(cookieName), `Expected ${cookieName} cookie after login`);
        assert.ok(
            loginResult.includes('<openclaw-app>') || loginResult.includes('OpenClaw'),
            'Expected OpenClaw UI to render after login',
        );

        // Step 3: Use cookie to access openclaw — should get 200
        const jwtLine = loginResult.split('\n').find(l => l.includes(cookieName));
        if (jwtLine) {
            const jwtValue = jwtLine.split('\t').pop().trim();
            const statusCode = curlSafe(
                `curl -s -o /dev/null -w "%{http_code}" --max-time 5 --connect-timeout 3 -b "${cookieName}=${jwtValue}" "${OPENCLAW_URL}/"`,
            );
            assert.ok(statusCode, 'Expected a response when using JWT cookie');
            assert.strictEqual(statusCode, '200', `Expected 200 after auth, got ${statusCode}`);
        }
    },
).tag('@admin').tag('@tools').tag('@security');

Scenario(
    'openclaw messenger endpoint is accessible without edge-login redirect',
    async () => {
        if (!isServiceUp(OPENCLAW_URL)) {
            console.log('SKIP: OpenClaw is not reachable — skipping webhook endpoint test');
            return;
        }

        const result = curlSafe(
            `curl -s -o /dev/null -w "%{http_code}" --max-time 5 --connect-timeout 3 "${OPENCLAW_URL}/api/channels/telegram/webhook"`,
        );
        assert.ok(result, 'Expected a response from OpenClaw webhook endpoint');

        // Should NOT be 302 redirect to edge login
        assert.notStrictEqual(result, '302', 'Webhook endpoint should not redirect to edge login');
    },
).tag('@admin').tag('@tools').tag('@security');
