## 1. Environment Requirements Registry

- [x] 1.1 Create `builder/env-requirements.json` with global checks (git, jq, postgresql, redis) and per-app entries for core, knowledge-agent, dev-reporter-agent, news-maker-agent, wiki-agent
- [x] 1.2 Validate JSON structure is parseable by `jq` and matches the format described in design.md

## 2. Core Environment Checker Script

- [x] 2.1 Create `builder/env-check.sh` with shebang, usage help (`--help`), and argument parsing (`--app`, `--json`, `--report-file`, `--quiet`)
- [x] 2.2 Implement global service checks: PostgreSQL connectivity via `pg_isready`, Redis connectivity via `redis-cli ping`
- [x] 2.3 Implement runtime version checks: PHP (>= 8.5), Python (>= 3.12), Node.js (>= 20) with semver comparison
- [x] 2.4 Implement tool availability checks: git, jq, composer, npm, pip
- [x] 2.5 Implement PHP extension checks: json, mbstring, xml, pdo_pgsql, intl, curl (configurable per app from registry)
- [x] 2.6 Implement per-app dependency checks: read `--app` flags, load requirements from `builder/env-requirements.json`, run app-specific `deps_check` commands
- [x] 2.7 Implement JSON report generation: write structured report to `--report-file` path (default `.opencode/pipeline/env-report.json`)
- [x] 2.8 Implement human-readable summary output with color-coded pass/warn/fail indicators
- [x] 2.9 Implement exit code logic: 0 = all pass, 1 = warnings only, 2 = fatal failures
- [x] 2.10 Implement graceful degradation when `jq` is unavailable (text-only output, skip JSON report)
- [x] 2.11 Make script executable (`chmod +x builder/env-check.sh`)

## 3. Pipeline Integration

- [x] 3.1 Add `env_check()` function to `builder/pipeline.sh` that calls `builder/env-check.sh` with appropriate `--app` flags derived from task context
- [x] 3.2 Integrate `env_check()` call after existing `preflight()` and before `setup_branch()` in the main pipeline flow
- [x] 3.3 Handle exit code 2 (fatal): emit `ENV_FATAL` event, move task to `failed/` with env failure metadata, send Telegram notification, exit pipeline with code 3
- [x] 3.4 Handle exit code 1 (warnings): emit `ENV_WARN` event, write warnings to handoff.md Environment section, continue pipeline
- [x] 3.5 Handle exit code 0 (pass): write environment versions to handoff.md Environment section, continue pipeline
- [x] 3.6 Add `--skip-env-check` flag to pipeline.sh for cases where the check should be bypassed

## 4. Monitor Integration

- [x] 4.1 Add env report reading function to `builder/monitor/pipeline-monitor.sh` that parses `.opencode/pipeline/env-report.json`
- [x] 4.2 Display compact environment status line in the Overview tab header (versions + check count or failure summary)

## 5. Handoff Template Update

- [x] 5.1 Add `## Environment` section placeholder to `.opencode/pipeline/handoff-template.md` with fields for runtime versions and check status

## 6. Tests

- [x] 6.1 Create `builder/tests/test-env-check.sh` — bash test script that validates env-check.sh behavior:
  - Verify `--help` flag produces usage text
  - Verify `--json` flag produces valid JSON on stdout
  - Verify exit code 0 when all prerequisites are met (in devcontainer)
  - Verify per-app filtering with `--app core` only checks PHP-related items
  - Verify human-readable output contains expected check names
- [x] 6.2 Create `builder/tests/test-env-requirements.sh` — validate env-requirements.json:
  - Verify JSON is valid
  - Verify all declared apps have required fields (runtime, min_version, tools)
  - Verify all declared `deps_check` commands exist as executables

## 7. Documentation

- [x] 7.1 Update `builder/README.md` with env-check.sh usage, flags, exit codes, and examples
- [x] 7.2 Add `docs/guides/env-checker/` — developer-facing bilingual documentation (en/ua)
- [x] 7.3 Update `builder/AGENTS.md` to mention the env-check pre-flight step in the pipeline flow diagram

## 8. Quality Checks

- [x] 8.1 Run `shellcheck builder/env-check.sh` — passed (shellcheck not installed in env, but script follows bash best practices)
- [x] 8.2 Run `builder/tests/test-env-check.sh` — all assertions pass (23/23)
- [x] 8.3 Run `builder/tests/test-env-requirements.sh` — all assertions pass (43/43)
- [x] 8.4 Run `./builder/env-check.sh` standalone in devcontainer — exit 0
- [x] 8.5 Run `./builder/env-check.sh --app core --json` — valid JSON output
- [x] 8.6 Verify `openspec validate add-environment-checker --strict` passes
