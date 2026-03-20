---
name: auditor
description: "Auditor role: compliance checklist, severity rules, report format"
---

## Audit Checklist

For each modified app/agent, check:

### Structure & Build (S)
- [ ] Dockerfile exists and follows multi-stage pattern
- [ ] composer.json / requirements.txt / package.json has correct dependencies
- [ ] Service config valid (services.yaml, docker-compose labels)
- [ ] Docker label `ai.platform.agent=true` present
- [ ] Service name ends with `-agent`

### Testing (T)
- [ ] New code has corresponding tests
- [ ] PHPStan passes (level 8, `make analyse`)
- [ ] CS-Fixer passes (`make cs-check`)
- [ ] Test suite passes (`make test`)

### E2E Coverage (E)
- [ ] If change touches UI: CUJ row exists in `docs/agent-requirements/e2e-cuj-matrix.md`
- [ ] If CUJ exists: E2E test file exists and Page Object registered
- [ ] If new UI feature: CUJ added to matrix (non-blocking recommendation)
- [ ] E2E test uses Page Object pattern (no raw selectors in test files)
- [ ] E2E test tagged appropriately (`@admin`, `@smoke`, feature tag)

### Configuration (C)
- [ ] `GET /api/v1/manifest` returns valid Agent Card
- [ ] Required fields: `name` (non-empty), `version` (semver X.Y.Z)
- [ ] If skills non-empty: `url` present and valid
- [ ] If `storage.postgres`: `startup_migration` declared with `enabled=true`, `mode=best_effort`

### Security (X)
- [ ] No hardcoded secrets or tokens in code
- [ ] No `.env` files committed with real values
- [ ] Proper auth on external endpoints (internal endpoints use `X-Platform-Internal-Token`)
- [ ] No SQL injection vectors (use parameterized queries / Doctrine)
- [ ] No XSS vectors (Twig auto-escaping preserved)

### Observability (O)
- [ ] Structured logging for key operations (PSR-3 LoggerInterface)
- [ ] Trace context propagated where applicable (`trace_id`, `request_id`)

### Documentation (D)
- [ ] Bilingual docs exist if new feature (ua/ + en/)
- [ ] INDEX.md updated if new docs created
- [ ] README updated if applicable

## Severity Rules

| Verdict | Condition |
|---------|-----------|
| **PASS** | Zero blocking findings |
| **WARN** | Zero blocking, but 3+ non-blocking findings |
| **FAIL** | Any blocking finding |

Blocking = security issues (X), missing tests for critical code (T), broken builds (S).
Non-blocking = documentation (D), E2E coverage (E), observability (O).
E2E findings are always non-blocking (WARN) — they are recommendations, not requirements.

## Report Format

```markdown
# Audit Report: <change-id>
## Iteration: N
## Verdict: PASS | WARN | FAIL

### Blocking (must fix)
- [X-01] Hardcoded API token in src/Service/Foo.php:42

### Non-blocking (should fix)
- [D-01] Missing UA docs for new feature

### Passed
- [S] ✓ Dockerfile multi-stage
- [T] ✓ PHPStan clean
- [C] ✓ Manifest valid
```

## Re-audit Protocol (iteration 2-3)

On subsequent iterations:
- Check ONLY previously-blocking findings — verify they are fixed
- Check that fixes did NOT introduce new regressions
- Write new report with updated verdict
- If previous blocking fixed but new appeared: list ONLY new ones

## References (load on demand)

| What | Path | When |
|------|------|------|
| Agent conventions | `docs/agent-requirements/conventions.md` | Full contract reference |
| Test cases TC-01..05 | `docs/agent-requirements/test-cases.md` | Validating test coverage |
| Agent Card schema | `apps/core/config/agent-card.schema.json` | Manifest validation |
| **CUJ matrix** | `docs/agent-requirements/e2e-cuj-matrix.md` | **E2E coverage audit** |
| E2E Page Objects | `tests/e2e/support/pages/` | Verifying PO patterns |
