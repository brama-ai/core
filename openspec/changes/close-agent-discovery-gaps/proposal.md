# Change: Close remaining agent discovery gaps

## Why

The `refactor-agent-discovery` change (Traefik-based pull discovery) is ~80% implemented in code
but 0/43 tasks are checked off. A thorough audit reveals 7 concrete gaps between the original
specification and the current codebase. These gaps affect test coverage, scheduled automation,
admin UX completeness, and schema-based validation — all of which are required before the
`refactor-agent-discovery` change can be archived.

This proposal tracks **only the remaining work** — it does not re-specify anything already
implemented and verified.

## What Changes

1. **Agent manifest JSON Schema** — Create `config/agent-manifest-schema.json` so that
   `AgentConventionVerifier` can validate manifests against a formal schema instead of inline
   PHP rules. Align the test-side schema (`tests/agent-conventions/support/manifest-schema.json`)
   with the same source of truth.

2. **Scheduled discovery polling** — Register `agent:discovery` as a scheduled task running
   every 60 seconds via the platform's existing scheduler infrastructure, so agents are
   discovered automatically without manual intervention.

3. **"Add by URL" admin stub** — Add the placeholder button and modal to the agents admin page
   with a "Функціонал в розробці" message, as specified in the original design.

4. **AgentConventionVerifier unit test coverage** — Expand unit tests to cover: missing `name`,
   missing `version`, missing `a2a_endpoint` when capabilities are declared, and null/invalid
   JSON input. Currently only 2 test cases exist (postgres migration contract).

5. **Convention test schema file** — Create `tests/agent-conventions/support/manifest-schema.json`
   mirroring the core schema for test-side validation.

6. **Quality verification** — Run PHPStan level 8, PHP CS Fixer, and all Codeception suites
   on discovery-related code to confirm zero regressions.

7. **Documentation verification** — Verify `docs/agent-requirements/conventions.md` and
   `docs/agent-requirements/test-cases.md` accuracy; update `LOCAL_DEV.md` with "Adding a new
   agent" section; add pointer in root `AGENTS.md` to conventions docs.

8. **Mark refactor-agent-discovery tasks complete** — After all gaps are closed, update
   `refactor-agent-discovery/tasks.md` to reflect actual completion state (check off all
   implemented tasks, note deviations).

## Impact

- Affected specs: `agent-conventions` (ADDED — new capability spec), `agent-registry` (MODIFIED — scheduled discovery)
- Affected code:
  - NEW `config/agent-manifest-schema.json`
  - NEW `tests/agent-conventions/support/manifest-schema.json`
  - MODIFIED `src/src/A2AGateway/AgentConventionVerifier.php` (optional: use schema-based validation)
  - MODIFIED `src/templates/admin/agents.html.twig` (add "Add by URL" button + modal)
  - NEW `src/tests/Unit/A2AGateway/AgentConventionVerifierTest.php` (expanded test cases)
  - MODIFIED scheduler config (register `agent:discovery` at 60s interval)
  - MODIFIED `docs/LOCAL_DEV.md` (new agent checklist section)
- No breaking changes
- No database migrations
- No new dependencies
