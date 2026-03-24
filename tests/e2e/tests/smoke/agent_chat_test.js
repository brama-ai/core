const assert = require('assert');
const { execSync } = require('child_process');
const path = require('path');

const PROJECT_ROOT = path.resolve(__dirname, '../../../..');

/**
 * Run `agent:chat` inside the core container with piped input.
 *
 * @param {string} input  Lines to pipe (newline-separated)
 * @param {number} timeoutMs
 * @returns {string} Combined stdout+stderr
 */
function runAgentChat(input, timeoutMs = 60_000) {
    return execSync(
        `echo "${input}" | docker exec -i brama-core-e2e-1 php bin/console agent:chat --username=e2e --language=uk`,
        { cwd: PROJECT_ROOT, timeout: timeoutMs, encoding: 'utf-8', stdio: ['pipe', 'pipe', 'pipe'] },
    );
}

Feature('Smoke: agent:chat Console Command');

Scenario('starts and loads platform tools', async () => {
    const output = runAgentChat('exit');

    assert.ok(output.includes('Agent Chat'), 'must print Agent Chat header');
    assert.ok(output.includes('tool(s) loaded from platform agents'), 'must report loaded tools');
    assert.ok(output.includes('Goodbye!'), 'must print Goodbye on exit');
}).tag('@smoke').tag('@agent-chat').tag('@p1');

Scenario('responds to a simple message without tool call', async () => {
    const output = runAgentChat('скажи одне слово: тест\\nexit');

    assert.ok(output.includes('Assistant:'), 'must print Assistant: prefix');
    assert.ok(output.includes('Goodbye!'), 'must exit cleanly');
}).tag('@smoke').tag('@agent-chat').tag('@p1');

Scenario('invokes hello.greet tool when asked to greet', async () => {
    const output = runAgentChat('привітай користувача E2EBot\\nexit');

    assert.ok(output.includes('[tool] hello.greet'), 'must call hello.greet tool');
    assert.ok(output.includes('[completed]'), 'tool result must be completed');
    assert.ok(output.includes('Assistant:'), 'must print final assistant response');
}).tag('@smoke').tag('@agent-chat').tag('@p1');

Scenario('handles empty input gracefully', async () => {
    const output = runAgentChat('\\nexit');

    assert.ok(output.includes('Goodbye!'), 'must exit cleanly after empty input');
}).tag('@smoke').tag('@agent-chat').tag('@p1');
