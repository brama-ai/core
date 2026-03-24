# Design: Unified Pipeline Agents

## Context
The current pipeline uses multiple prompt variants per role:
- Builder-oriented primary agents such as `architect.md`, `coder.md`, `tester.md`
- Sisyphus-oriented subagents such as `s-architect.md`, `s-coder.md`, `s-tester.md`
- partial universal variants such as `u-investigator.md`

These variants encode orchestration details inside role prompts. That makes prompt behavior drift over time and turns `.opencode/pipeline/handoff.md` into an implicit, inconsistent dependency.

## Decision
Move to a unified `u-*` role model:
- every role has one canonical `u-*` prompt definition
- orchestration-specific behavior lives in the caller prompt and runtime permissions, not in duplicated role prompts
- prompt `CONTEXT` is the primary context source
- `handoff.md` access is opt-in and exception-based

## Audit And Security Decisions

### Auditor
`auditor` becomes a fix-capable quality gate, not a read-only reviewer:
- it runs immediately after `coder`
- it may directly apply safe fixes related to the required task
- it must append remediation details to the outgoing context
- downstream validation and automated tests must cover both coder and auditor changes
- if the remaining issues still require implementation work, it returns the task to `coder` with explicit follow-up context

This makes `auditor` the first structured quality pass after implementation instead of a late advisory checkpoint.

### Security-Review
`security-review` remains non-fixing:
- it does not patch source code directly
- it produces findings and remediation guidance
- if findings require behavior, contract, or security-pattern changes, it creates a follow-up OpenSpec proposal/task request

This keeps security remediation explicit and spec-driven instead of allowing silent architectural/security drift through direct ad hoc fixes.

## Agent Contract
Default contract:
- incoming `CONTEXT` is authoritative
- agents must not read `handoff.md` unless explicitly allowed
- missing context must cause a stop with a precise request for the missing fields

Exceptions:
- `summarizer` may read `handoff.md` as primary aggregation input
- `planner` may read `handoff.md` only for resume or continuity use cases if required by the caller

## Migration Strategy
1. Add a shared context contract document.
2. Convert one role at a time to a unified `u-*` prompt.
3. Update callers so they pass the required `CONTEXT`.
4. Verify Builder and Ultraworks can both invoke the same unified role definition safely.
5. Reorder pipeline callers so `auditor` runs immediately after `coder` and before validation/tests where applicable.
6. Remove old `primary` / `s-*` duplicates only after equivalent `u-*` coverage exists and the docs are updated.

## Risks
- Pipeline callers may still assume hidden `handoff.md` reads.
- Some roles currently differ in permissions, not just wording. These need explicit runtime controls before prompt files can be merged.
- Making `auditor` fix-capable increases the need for clean remediation context and strong downstream test coverage.
- `security-review` follow-up generation must be structured enough that humans can distinguish advisory notes from required spec/proposal work.
- Documentation and matrices may drift again if source-of-truth files are not updated in the same change.

## Non-Goals
- Changing model routing policy beyond what is required for prompt unification
- Reworking unrelated pipeline phases or cost/concurrency logic
