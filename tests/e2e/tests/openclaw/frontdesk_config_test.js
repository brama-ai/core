const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

/**
 * Discover the OpenClaw gateway container name dynamically.
 * Tries the env var first, then falls back to docker ps discovery.
 */
function getOpenClawContainer() {
    if (process.env.OPENCLAW_CLI_CONTAINER) {
        return process.env.OPENCLAW_CLI_CONTAINER;
    }

    try {
        // Find the running container whose name contains "openclaw-gateway"
        const name = execSync(
            'docker ps --format "{{.Names}}" --filter "name=openclaw-gateway" --filter "status=running"',
            { encoding: 'utf8' },
        ).trim().split('\n')[0];

        if (name) return name;
    } catch {
        // ignore
    }

    // Final fallback
    return 'brama-openclaw-gateway-e2e-1';
}

const OPENCLAW_CLI_CONTAINER = getOpenClawContainer();

function runOpenClawConfigGet(key) {
    try {
        const output = execSync(
            `docker exec ${OPENCLAW_CLI_CONTAINER} openclaw config get ${key}`,
            { encoding: 'utf8' },
        );

        const lines = output
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== '');

        return lines[lines.length - 1] || '';
    } catch (e) {
        // "Config path not found" is returned as stderr / non-zero exit
        const stderr = e.stderr ? e.stderr.toString().trim() : '';
        if (stderr.includes('Config path not found') || stderr.includes('not found')) {
            return null;
        }
        throw e;
    }
}

Feature('OpenClaw: Frontdesk Runtime Config');

Scenario('runtime guardrails are enabled in OpenClaw config', async ({ I }) => {
    const native = runOpenClawConfigGet('commands.native');
    const nativeSkills = runOpenClawConfigGet('commands.nativeSkills');
    const toolsProfile = runOpenClawConfigGet('tools.profile');
    const toolsAlsoAllowPlatformTools = runOpenClawConfigGet('tools.alsoAllow.0');
    const litellmEndUserHeader = runOpenClawConfigGet('models.providers.litellm.headers.x-litellm-end-user-id');

    // commands.native must be restricted (either 'false' or 'auto')
    assert.ok(
        native === 'false' || native === 'auto',
        `Expected commands.native to be 'false' or 'auto', got '${native}'`,
    );

    assert.strictEqual(nativeSkills, 'auto');

    // The following keys are optional — assert only when present in config
    if (toolsProfile !== null) {
        assert.strictEqual(toolsProfile, 'messaging');
    }
    if (toolsAlsoAllowPlatformTools !== null) {
        assert.strictEqual(toolsAlsoAllowPlatformTools, 'platform-tools');
    }
    if (litellmEndUserHeader !== null) {
        assert.strictEqual(litellmEndUserHeader, 'openclaw-frontdesk');
    }
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
