const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const OPENCLAW_CLI_CONTAINER = process.env.OPENCLAW_CLI_CONTAINER || 'ai-community-platform-openclaw-cli-1';

function runOpenClawConfigGet(key) {
    const output = execSync(
        `docker exec ${OPENCLAW_CLI_CONTAINER} openclaw config get ${key}`,
        { encoding: 'utf8' },
    );

    const lines = output
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');

    return lines[lines.length - 1] || '';
}

Feature('OpenClaw: Frontdesk Runtime Config');

Scenario('runtime guardrails are enabled in OpenClaw config', async ({ I }) => {
    const native = runOpenClawConfigGet('commands.native');
    const nativeSkills = runOpenClawConfigGet('commands.nativeSkills');
    const toolsProfile = runOpenClawConfigGet('tools.profile');
    const toolsAlsoAllowPlatformTools = runOpenClawConfigGet('tools.alsoAllow.0');
    const litellmEndUserHeader = runOpenClawConfigGet('models.providers.litellm.headers.x-litellm-end-user-id');

    assert.strictEqual(native, 'false');
    assert.strictEqual(nativeSkills, 'auto');
    assert.strictEqual(toolsProfile, 'messaging');
    assert.strictEqual(toolsAlsoAllowPlatformTools, 'platform-tools');
    assert.strictEqual(litellmEndUserHeader, 'openclaw-frontdesk');
}).tag('@openclaw').tag('@config').tag('@p0');

Scenario('frontdesk workspace files exist', async ({ I }) => {
    const workspace = path.resolve(process.cwd(), '../../.local/openclaw/state/workspace');
    const required = [
        'IDENTITY.md',
        'USER.md',
        'SOUL.md',
        'AGENTS.md',
        'TOOLS.md',
        'HEARTBEAT.md',
        'BOOTSTRAP.md',
        'MEMORY.md',
    ];

    for (const filename of required) {
        const filePath = path.join(workspace, filename);
        const exists = fs.existsSync(filePath);
        assert.ok(exists, `Expected workspace file: ${filename}`);
    }

    assert.ok(true);
}).tag('@openclaw').tag('@config').tag('@p0');
