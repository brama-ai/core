---
name: planner
description: "Planner role: task analysis, profile selection, pipeline-plan.json output"
---

## Pipeline Profiles

| Profile | Agents | Use Case |
|---------|--------|----------|
| docs-only | documenter, summarizer | Documentation, README, no code |
| quality-gate | coder, validator, summarizer | Fix lint/phpstan/cs errors only |
| tests-only | coder, tester, summarizer | Write missing tests, no new features |
| quick-fix | coder, validator, summarizer | Typos, config, 1-3 files |
| standard | coder, validator, tester, summarizer | Normal feature, one app |
| standard+docs | coder, validator, tester, documenter, summarizer | Feature + bilingual docs |
| complex | coder, validator, tester, summarizer | Multi-service, migrations, API changes |
| complex+agent | coder, auditor, validator, tester, summarizer | Creates/modifies an agent |

## Timeout Reference

| Profile | Coder | Validator | Architect | Summarizer |
|---------|-------|-----------|-----------|------------|
| quick-fix | 900s | 600s | — | 600s |
| standard | default | default | default | default |
| complex | 5400s | default | 3600s | 900s |

## Decision Rules

1. Always include `summarizer` as last agent
2. Exclude `architect` when OpenSpec `tasks.md` exists
3. Exclude `auditor` unless task modifies agent app (`apps/*-agent/`)
4. Exclude `tester` for docs-only or quality-gate tasks
5. Exclude `documenter` unless task mentions documentation
6. Include `validator` for any code change
7. Be conservative: if unsure, include the agent

## Quick Patterns

| Signal | Profile |
|--------|---------|
| "Finish change" + quality tasks only | quality-gate |
| "Finish change" + test tasks only | tests-only |
| "Implement change" + tasks.md exists | standard (no architect) |
| "Write docs" / "Update documentation" | docs-only |
| Modifies `apps/*-agent/` | add auditor |

## Analysis Steps

1. Read task description
2. Search codebase for mentioned files/patterns (glob/grep)
3. Check existing proposals: `openspec list`
4. Estimate: files affected, apps involved, services changed
5. Determine: migrations needed? API changes? Agent task?

## Output Format

Write `pipeline-plan.json` to repo root:

```json
{
  "profile": "standard",
  "reasoning": "OpenSpec tasks.md ready, single app, needs tests",
  "agents": ["coder", "validator", "tester", "summarizer"],
  "skip_openspec": true,
  "estimated_files": 8,
  "apps_affected": ["core"],
  "needs_migration": false,
  "needs_api_change": false,
  "is_agent_task": false,
  "timeout_overrides": {},
  "model_overrides": {}
}
```

## References (load on demand)

| What | Path | When |
|------|------|------|
| Existing proposals | `openspec list` | Check before planning |
| Profile configs | `.opencode/pipeline/profiles.json` | Timeout reference |
| Agent definitions | `.opencode/agents/` | Understanding capabilities |
