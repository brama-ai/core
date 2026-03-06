// TC-01: Agent Manifest Endpoint
// Validates that /api/v1/manifest returns a compliant response.
// Run with: AGENT_URL=http://knowledge-agent:80 npx codeceptjs run

Feature('TC-01 Agent Manifest Endpoint');

Scenario('GET /api/v1/manifest returns HTTP 200', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    I.assertEqual(res.status, 200, `Expected 200, got ${res.status}`);
});

Scenario('manifest Content-Type is application/json', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    I.assertContain(res.headers['content-type'], 'application/json');
});

Scenario('manifest has required field: name (non-empty string)', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    I.assertEqual(res.status, 200);
    const body = res.data;
    I.assertTruthy(body.name, 'field "name" must be present and non-empty');
    I.assertEqual(typeof body.name, 'string', 'field "name" must be a string');
});

Scenario('manifest field name follows kebab-case convention', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const { name } = res.data;
    const isKebab = /^[a-z][a-z0-9-]*$/.test(name);
    I.assertTrue(isKebab, `name "${name}" must be kebab-case (e.g. my-agent)`);
});

Scenario('manifest has required field: version (non-empty string)', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const body = res.data;
    I.assertTruthy(body.version, 'field "version" must be present and non-empty');
    I.assertEqual(typeof body.version, 'string', 'field "version" must be a string');
});

Scenario('manifest version follows semver X.Y.Z format', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const { version } = res.data;
    const isSemver = /^\d+\.\d+\.\d+$/.test(version);
    I.assertTrue(isSemver, `version "${version}" must follow semver X.Y.Z`);
});

Scenario('manifest has skills field as array', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const body = res.data;
    if (body.skills !== undefined) {
        I.assertTrue(Array.isArray(body.skills), 'skills must be an array');
    }
});

Scenario('manifest has a2a_endpoint when skills is non-empty', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const body = res.data;
    if (Array.isArray(body.skills) && body.skills.length > 0) {
        const endpoint = body.url || body.a2a_endpoint;
        I.assertTruthy(endpoint, 'url or a2a_endpoint is required when skills is non-empty');
        I.assertEqual(typeof endpoint, 'string');
    }
});
