# Audit Report Template

Use this structure for the audit report output.

## Header

```markdown
# Agent Audit Report
**Date**: YYYY-MM-DD
**Scope**: <agent name(s) or "All agents">
**Auditor**: Claude (agent-auditor skill)
```

## Per-Agent Section

For each agent:

```markdown
## <Agent Name>

**Stack**: PHP/Symfony | Python/FastAPI
**Overall**: X PASS | Y WARN | Z FAIL

### S: Structure & Build

| # | Check | Verdict | Finding |
|---|-------|---------|---------|
| S-01 | src/ directory exists | PASS | `apps/<agent>/src/` found |
| S-02 | ... | WARN | ... |

### T: Testing
(same table format)

### C: Configuration
(same format — skip for core)

### X: Security
(same format)

### O: Observability
(same format)

### D: Documentation
(same format)

### M: Database & Migrations
(same format — skip if agent has no storage)

### Q: Standards Compliance
(same format)
```

## Platform Summary

```markdown
## Platform Summary

| Agent | PASS | WARN | FAIL | Score |
|-------|------|------|------|-------|
| core | X | Y | Z | X/(X+Y+Z)% |
| hello-agent | ... | ... | ... | ...% |
| ... | ... | ... | ... | ...% |

### Overall Platform Score: XX%
```

## Recommendations

```markdown
## Recommendations

### Critical (FAIL)
1. **[AGENT] CHECK-ID**: What failed and how to fix it

### Important (WARN on Security / Testing)
1. **[AGENT] CHECK-ID**: Description and improvement suggestion

### Improvements (Other WARN)
1. **[AGENT] CHECK-ID**: Description and improvement suggestion
```

## Verdict Display

Use these labels in the Verdict column:
- **PASS** — requirement fully met
- **WARN** — partially met or could be improved; not blocking
- **FAIL** — requirement not met; should be addressed
