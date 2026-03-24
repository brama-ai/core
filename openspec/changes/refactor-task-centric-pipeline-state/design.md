# Design: Task-Centric Pipeline Runtime State

## Context

Foundry and Ultraworks currently model runtime continuity in different and partly incompatible ways:

- Foundry uses queue/status folders plus summary/artifact subtrees
- Ultraworks relies on task worktrees but still reads global-looking pipeline continuity files inside each checkout
- monitors infer state from markdown, mtimes, and folder placement

This works for simple happy paths but becomes weak when:
- a task is interrupted mid-step
- reviewer/auditor sends work back for rework
- operators need to inspect one task holistically
- multiple workflows should expose a similar operator mental model

## Goals

- Make one task directory the primary operator-facing runtime unit
- Keep all task-local artifacts together in one place
- Make resume deterministic from machine-readable state
- Preserve a readable handoff journal for humans and agents
- Support rework loops without overwriting historical progress
- Let monitors render state without parsing ad hoc markdown conventions

## Non-Goals

- Replacing git worktree isolation for Ultraworks
- Removing workflow-specific behavior such as Foundry queue polling or Ultraworks parallel phases
- Designing a database-backed scheduler in this change

## Canonical Layout

Each task run lives under:

```text
tasks/<task-slug>--<workflow>/
```

Where `<workflow>` is one of:
- `foundry`
- `ultraworks`

Each task directory contains:

```text
task.md
handoff.md
state.json
events.jsonl
summary.md
meta.json
```

### File Roles

- `task.md`
  - original task prompt or normalized task description
- `handoff.md`
  - readable inter-agent journal
  - may be used by explicitly allowed roles such as summarizer
  - not the primary resume source
- `state.json`
  - canonical machine-readable state
  - source of truth for monitor, status, and resume
- `events.jsonl`
  - append-only event stream for history and diagnostics
- `summary.md`
  - final or best-effort summary artifact for that specific task
- `meta.json`
  - immutable or slow-changing metadata such as workflow, created time, branch, worktree, run id, resumed-from lineage

## State Model

`state.json` tracks:

- task identity
- workflow
- overall status
- current step
- resume point
- step statuses
- attempt counts
- timestamps
- pointers to runtime resources

Recommended step statuses:

- `pending`
- `in_progress`
- `done`
- `failed`
- `blocked`
- `skipped`
- `rework_requested`
- `cancelled`

Example shape:

```json
{
  "task_id": "add-streaming-support--ultraworks",
  "workflow": "ultraworks",
  "status": "in_progress",
  "current_step": "coder",
  "resume_from": "coder",
  "started_at": "2026-03-24T13:00:00Z",
  "updated_at": "2026-03-24T13:12:00Z",
  "steps": [
    { "id": "architect", "status": "done", "attempt": 1, "started_at": "...", "completed_at": "..." },
    { "id": "coder", "status": "in_progress", "attempt": 2, "started_at": "...", "completed_at": null },
    { "id": "reviewer", "status": "rework_requested", "attempt": 1, "started_at": "...", "completed_at": "..." },
    { "id": "validator", "status": "pending", "attempt": 0, "started_at": null, "completed_at": null }
  ]
}
```

## Rework Visualization

The runtime should not model rework by simply deleting prior success. Instead:

- the step requesting changes moves to `rework_requested`
- the implementation step that must rerun moves back to `in_progress` or `pending`
- the rerun increments `attempt`
- `events.jsonl` records why the rework happened

This allows monitors to show:
- which step asked for rework
- which step is being rerun
- how many attempts have occurred

## Resume Model

Resume uses `state.json` first.

Rules:

1. If `state.json` exists and `status=in_progress`, the runtime resumes from `resume_from` or `current_step`.
2. If the interrupted step has no terminal event, the runtime reruns that step from the beginning.
3. If a step requested rework, resume starts from the designated rerun step, not the requester.
4. If `state.json` is missing or corrupted, the runtime may attempt best-effort recovery from `handoff.md` and `events.jsonl`.
5. If `summary.md` already exists and `status=completed`, resume should refuse unless the operator explicitly forces a rerun.

## Monitor Contract

Monitors should render task state from:

- `state.json` for current status and step progress
- `meta.json` for workflow/worktree/session metadata
- `events.jsonl` for history and diagnostics
- `handoff.md` for readable phase output
- `summary.md` for final task result

Monitors should not depend on:

- latest-summary scans across unrelated tasks
- queue placement alone to determine status
- implicit progress inferred only from handoff section order

## Migration Direction

Foundry and Ultraworks should converge onto the same task directory contract, while preserving runtime differences:

- Foundry may still poll task directories looking for `state.status=queued`
- Ultraworks may still create isolated worktrees per task
- both workflows should emit the same task-local artifacts

Compatibility wrappers or migration helpers may be needed temporarily for:

- `agentic-development/foundry-tasks/`
- legacy summary collectors
- tools that currently assume `.opencode/pipeline/handoff.md` is the only continuity file
