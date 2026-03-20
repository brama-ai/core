## Context

The builder pipeline (`builder/pipeline.sh`) orchestrates multiple AI agents (architect, coder, validator, tester, etc.) to complete development tasks. Each agent consumes tokens and time. When the environment is broken (database down, missing tools), agents either fail cryptically or produce unusable output. There is no systematic pre-flight check that validates the full environment before committing to a pipeline run.

The existing `preflight()` function in `pipeline.sh` checks only for `opencode` CLI, Docker daemon, and git state. It does not validate language runtimes, package managers, database connectivity, or per-app dependencies.

**Stakeholders**: Pipeline users (developers), pipeline monitor, downstream agents.

**Constraints**:
- Pure bash (no Python/Node dependencies for the checker itself)
- Must work inside the devcontainer (Ubuntu noble, PHP 8.5, Python 3.12, Node 22, PostgreSQL 16, Redis 7)
- Must be independently runnable outside the pipeline
- Must not add startup latency > 5 seconds for a full check

## Goals / Non-Goals

**Goals**:
- Cancel pipeline tasks immediately when fatal prerequisites are missing
- Provide clear, actionable diagnostics for every failed check
- Support per-app requirement scoping (only check Python for news-maker tasks)
- Produce machine-readable output for monitor integration
- Be independently runnable for developer self-service

**Non-Goals**:
- Auto-fix missing prerequisites (future extensibility, not in this change)
- Replace the existing `preflight()` function (complement it)
- Check network connectivity to external services (Telegram API, OpenRouter, etc.)
- Validate AI provider API keys or model availability

## Decisions

### Decision 1: Separate script, not inline in pipeline.sh

The environment checker lives in `builder/env-check.sh` as a standalone script rather than being inlined into `pipeline.sh`.

**Rationale**: Independent testability, reusability (developers can run it manually), and separation of concerns. The pipeline calls it as a subprocess and reacts to exit codes.

**Alternatives considered**:
- Inline in `pipeline.sh`: Rejected — makes pipeline.sh even larger, not independently testable.
- Python script: Rejected — adds a dependency on Python being available (circular for checking Python).

### Decision 2: Three-tier exit codes (0/1/2)

- `0` = All checks pass. Pipeline proceeds.
- `1` = Some checks failed but are non-fatal (warnings). Pipeline proceeds with degraded capability noted in handoff.
- `2` = Fatal prerequisite missing. Pipeline MUST cancel the task.

**Rationale**: Mirrors common Unix conventions. Exit 1 allows the pipeline to continue when, e.g., Docker is unavailable but the task doesn't need tests. Exit 2 is reserved for truly blocking failures (no PHP for a PHP task, no database).

**Alternatives considered**:
- Binary pass/fail (0/1): Rejected — too coarse. Some missing tools are warnings (Docker for non-test tasks), others are fatal.
- Granular exit codes (0-10): Rejected — over-engineering for bash; the JSON report carries detailed per-check results.

### Decision 3: Per-app requirement registry in JSON

A `builder/env-requirements.json` file declares what each app needs:

```json
{
  "$schema": "builder/env-requirements.schema.json",
  "global": {
    "tools": ["git", "jq"],
    "services": ["postgresql", "redis"]
  },
  "apps": {
    "core": {
      "runtime": "php",
      "min_version": "8.5",
      "tools": ["composer"],
      "extensions": ["json", "mbstring", "xml", "pdo_pgsql", "intl", "curl"],
      "deps_check": "composer check-platform-reqs"
    },
    "knowledge-agent": {
      "runtime": "php",
      "min_version": "8.5",
      "tools": ["composer"],
      "extensions": ["json", "mbstring", "xml", "pdo_pgsql"],
      "deps_check": "composer check-platform-reqs"
    },
    "dev-reporter-agent": {
      "runtime": "php",
      "min_version": "8.5",
      "tools": ["composer"],
      "extensions": ["json", "mbstring", "xml", "pdo_pgsql"],
      "deps_check": "composer check-platform-reqs"
    },
    "news-maker-agent": {
      "runtime": "python",
      "min_version": "3.12",
      "tools": ["pip"],
      "deps_check": "pip check"
    },
    "wiki-agent": {
      "runtime": "node",
      "min_version": "20",
      "tools": ["npm"],
      "deps_check": "npm ls --all"
    }
  }
}
```

**Rationale**: Declarative, easy to extend when new apps are added. The checker reads this file and validates only the relevant subset based on `--app` flags or task context.

**Alternatives considered**:
- Hardcoded checks in bash: Rejected — not maintainable as apps grow.
- Per-app `.env-check` files: Rejected — scattered, harder to validate holistically.
- YAML format: Rejected — requires a YAML parser; JSON is natively parseable with `jq`.

### Decision 4: JSON report format

The checker writes a structured report to `.opencode/pipeline/env-report.json`:

```json
{
  "timestamp": "2026-03-19T12:00:00Z",
  "exit_code": 0,
  "summary": "All 12 checks passed",
  "duration_ms": 1200,
  "checks": [
    {
      "name": "postgresql",
      "category": "service",
      "status": "pass",
      "detail": "PostgreSQL 16.2 accepting connections on localhost:5432",
      "required_by": ["global"]
    },
    {
      "name": "php_version",
      "category": "runtime",
      "status": "pass",
      "detail": "PHP 8.5.1 (>= 8.5 required)",
      "required_by": ["core", "knowledge-agent"]
    },
    {
      "name": "docker",
      "category": "tool",
      "status": "warn",
      "detail": "Docker daemon not running (tests will be skipped)",
      "required_by": []
    }
  ],
  "environment": {
    "php": "8.5.1",
    "python": "3.12.4",
    "node": "22.3.0",
    "composer": "2.8.1",
    "npm": "10.8.0",
    "postgresql": "16.2",
    "redis": "7.2.5"
  }
}
```

**Rationale**: Enables the monitor to display per-check status, enables future tooling (dashboards, CI integration), and provides version info for handoff enrichment.

### Decision 5: Integration point in pipeline.sh

The env check runs **after** the existing `preflight()` and **before** branch setup:

```
preflight()          ← existing: checks opencode, docker, git
env_check()          ← NEW: checks runtimes, services, per-app deps
setup_branch()       ← existing: creates pipeline branch
run_planner()        ← existing: determines agent sequence
run_agents()         ← existing: runs agent sequence
```

On exit code 2, the pipeline:
1. Emits an `ENV_FATAL` event
2. Moves the task file to `failed/` with env failure metadata
3. Sends Telegram notification (if enabled)
4. Exits with code 3 (new: distinguishes env failure from agent failure)

On exit code 1, the pipeline:
1. Emits an `ENV_WARN` event
2. Writes warnings to handoff.md
3. Continues execution

**Rationale**: Minimal disruption to existing flow. The env check is a gate, not a replacement for preflight.

### Decision 6: Monitor integration approach

The monitor reads `.opencode/pipeline/env-report.json` and displays a compact status line in the Overview tab header:

```
Env: PHP 8.5 | Python 3.12 | Node 22 | PG | Redis | 12/12 checks
```

Or on failure:
```
Env: FAILED - PostgreSQL not available, PHP mbstring missing (2 fatal)
```

This is a read-only integration — the monitor never runs the checker itself.

## Risks / Trade-offs

- **Risk**: `jq` not available in some environments.
  **Mitigation**: The checker already validates `jq` presence as its first check. If `jq` is missing, it falls back to a simplified text-only report and warns that JSON output is unavailable.

- **Risk**: False positives blocking valid tasks (e.g., checking Python for a PHP-only task).
  **Mitigation**: Per-app scoping via `--app` flags. The pipeline passes `--app` based on planner output or task context. Without `--app`, only global checks run.

- **Risk**: Checker adds latency to pipeline startup.
  **Mitigation**: All checks are local (no network calls except `pg_isready` and `redis-cli ping` to localhost). Target: < 3 seconds for full check suite.

- **Trade-off**: JSON report requires `jq` for generation.
  **Mitigation**: Acceptable — `jq` is already used extensively in `pipeline.sh` and is present in the devcontainer. The checker gracefully degrades without it.

## Future Extensibility: Auto-Fix Mode

A future `--auto-fix` flag will attempt to resolve fixable issues by invoking patterns from the `devcontainer-provisioner` skill:

```bash
./builder/env-check.sh --app core --auto-fix
```

This is explicitly **out of scope** for this change but the architecture supports it:
- Each check in the registry can declare a `fix_command`
- The checker's JSON report includes enough detail for a fixer to act on
- Exit code 1 (fixable) vs 2 (fatal) already distinguishes fixable from unfixable

## Open Questions

None — all design decisions are resolved.
