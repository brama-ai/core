---
name: agent-auditor
description: >
  Audit agents in the AI Community Platform for structure, testing, security,
  observability, documentation, and standards compliance. Use when the user asks
  to audit, review, check, or validate an agent's quality, compliance, or
  readiness. Triggers on: "audit", "compliance", "agent check", "quality gate",
  "convention check", "readiness review", "platform standards".
---

# Agent Auditor

Audit one or all platform agents against AI Community Platform conventions and
quality standards. Produce a structured report with PASS / WARN / FAIL verdicts
and actionable recommendations.

## When to Use

- User asks to audit a specific agent (e.g., "audit hello-agent")
- User asks to audit all agents (e.g., "audit all agents", "run platform audit")
- User wants to check readiness of a new agent before merge
- User asks about compliance with platform conventions

## Workflow

### Step 1 — Determine Scope

Ask the user or infer from context:

1. **Which agents?** A single agent name (directory under `apps/`) or `all`.
2. **Which categories?** All by default, or a specific subset.

Agent discovery — list directories under `apps/`:
- PHP agents: contain `composer.json`
- Python agents: contain `requirements.txt`

`core` is the platform hub, **not** a registered agent. Audit it for
structure / testing / security / observability but skip agent-specific checks
(manifest endpoint, compose `ai.platform.agent` label, A2A).

### Step 2 — Read the Checklist

Based on agent stack, read the matching reference file:
- PHP/Symfony → `references/checklist-php.md`
- Python/FastAPI → `references/checklist-python.md`
- Cross-agent → `references/checklist-platform.md`

### Step 3 — Execute Checks

For every item in the checklist:

1. Use Glob, Grep, Read to verify the condition.
2. Record verdict: **PASS**, **WARN**, or **FAIL**.
3. Note the specific finding (what was found or what is missing).
4. Add a recommendation for WARN / FAIL verdicts.

**IMPORTANT**: Actually read files. Do not guess. Missing file = FAIL for
checks that require it.

### Step 4 — Generate Report

Read `references/report-template.md` for the output format. Produce:
- One section per agent with per-category tables.
- A platform summary with scores.
- Prioritized recommendations.

### Step 5 — Recommendations

After tables, output a prioritized action list:
1. **Critical** (FAIL) — must fix
2. **Important** (WARN on security / testing) — fix soon
3. **Improvement** (other WARN) — nice to have

## Audit Categories

| ID | Category | Applies To |
|----|----------|-----------|
| S | Structure & Build | All |
| T | Testing | All |
| C | Configuration | Agents only (not core) |
| X | Security | All |
| O | Observability | All |
| D | Documentation | All |
| M | Database & Migrations | Agents with storage |
| Q | Standards Compliance | All |

## Key File Locations

| Resource | Path |
|----------|------|
| Agent source | `apps/<agent>/` |
| Dockerfiles | `docker/<agent>/Dockerfile` |
| Compose config | `compose.yaml` |
| Convention spec | `docs/agent-requirements/conventions.md` |
| Agent Card schema | `apps/core/config/agent-card.schema.json` |
| Observability spec | `docs/agent-requirements/observability-requirements.md` |
| Storage spec | `docs/agent-requirements/storage-provisioning.md` |
| Test case spec | `docs/agent-requirements/test-cases.md` |
| Agent PRDs | `docs/agents/en/<agent-prd>.md` |
| Doc index | `index.md` |
| Makefile | `Makefile` |

## CI Conversion Notes

To convert to a CI script later:
1. Extract checklist items into shell/Python performing the same fs checks.
2. Output JSON for machine consumption.
3. Add `make audit` target.
4. Exit 0 = all pass, exit 1 = any FAIL.
