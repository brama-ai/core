# Pipeline Handoff Template

This template defines the standard sections for agent handoff during pipeline execution.

## Task

- **Description**: [Task description]
- **Started**: [ISO timestamp]
- **Branch**: [Git branch]
- **Pipeline ID**: [Pipeline identifier]
- **Profile**: [Profile name]

---

## Environment

> Populated by `builder/env-check.sh` during pre-flight check.

**Runtime Versions**:
- PHP: [version]
- Python: [version]
- Node: [version]
- Composer: [version]

**Services**:
- PostgreSQL: [version or "not available"]
- Redis: [version or "not available"]

**Check Status**: [pass/warn/fail] — [summary]

---

## Architect

- **Status**: pending
- **Task**: [Description]

---

## Coder

- **Status**: pending
- **Task**: [Description]

---

## Validator

- **Status**: pending
- **Task**: [Description]

---

## Tester

- **Status**: pending
- **Task**: [Description]

---

## Documenter

- **Status**: pending
- **Task**: [Description]

---

## Summarizer

- **Status**: pending

---

## Recommended follow-up tasks

> Agents add items here when they notice work outside their current scope.
> Summarizer collects these into actionable task recommendations.

## Notes

Use this section for any cross-agent notes or important context.