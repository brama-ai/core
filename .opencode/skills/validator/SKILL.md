---
name: validator
description: "Validator role: static analysis workflow, PHPStan/CS-Fixer rules, per-app targets"
---

## Per-App Validation Targets

| App | CS Check | CS Fix | Analyse |
|-----|----------|--------|---------|
| apps/core/ | `make cs-check` | `make cs-fix` | `make analyse` |
| apps/knowledge-agent/ | `make knowledge-cs-check` | `make knowledge-cs-fix` | `make knowledge-analyse` |
| apps/hello-agent/ | `make hello-cs-check` | `make hello-cs-fix` | `make hello-analyse` |
| apps/dev-reporter-agent/ | `make dev-reporter-cs-check` | `make dev-reporter-cs-fix` | `make dev-reporter-analyse` |
| apps/news-maker-agent/ | `make news-cs-check` | `make news-cs-fix` | `make news-analyse` |
| apps/wiki-agent/ | — | — | `make wiki-build` |

## Tool Configuration

| Tool | Config | Rules |
|------|--------|-------|
| PHPStan | `phpstan.neon` per app | Level 8 (strictest), paths: `src/`, excludes: `var/` |
| PHP CS Fixer | `.php-cs-fixer.dist.php` | `@Symfony` ruleset, excludes: `var/`, `config/bundles.php`, `config/reference.php` |
| ruff | `pyproject.toml` | Python apps only (news-maker-agent) |

## Workflow

1. Identify changed apps from context
2. Run `cs-fix` first (auto-fixable issues)
3. Run `cs-check` to verify (should pass after fix)
4. Run `analyse` (PHPStan) — fix manually
5. Iterate until zero errors across all changed apps

## Fix Strategy

| Issue Type | Action |
|-----------|--------|
| CS formatting | `make <app>-cs-fix` auto-fixes |
| Missing return type | Add explicit return type annotation |
| Nullable not handled | Add null check or `??` default |
| Type mismatch | Fix the type, not the tool config |
| Baseline suppression | Preserve existing `phpstan-baseline.neon` — only fix NEW errors |
| Design-level error | Document in handoff, do NOT change architecture |

## Rules

- Fix ONLY reported issues — do not refactor surrounding code
- Keep fixes minimal — prefer annotations over restructuring
- If `cs-fix` introduces new PHPStan errors, resolve the conflict
- Do NOT modify test files — only production code (when running parallel with tester)

## References (load on demand)

| What | Path | When |
|------|------|------|
| PHPStan config | `apps/<app>/phpstan.neon` | Understanding error context |
| CS Fixer config | `apps/<app>/.php-cs-fixer.dist.php` | Understanding rule exceptions |
| Baseline | `apps/<app>/phpstan-baseline.neon` | Checking existing suppressions |
