# Batch Pipeline Runner

## Overview

`pipeline-batch.sh` runs a list of tasks through the multi-agent pipeline (architect → coder → validator → tester → documenter). Each task is executed as a separate `pipeline.sh` invocation.

Two input formats:
- **File** (`tasks.txt`) — one line = one task (simple format)
- **Folder** (`tasks/`) — one `.md` file = task with full description (kanban)

For overnight runs, parallel execution is supported: `--workers N` runs up to N tasks simultaneously using isolated git worktrees.

## Quick Start

### Option 1: Task folder (recommended)

Each task is a separate `.md` file with a detailed prompt:

```
tasks/
├── todo/                         ← tasks waiting to run
│   ├── add-streaming-support.md
│   └── fix-session-timeout.md
├── in-progress/                  ← currently running
├── done/                         ← completed successfully
└── failed/                       ← completed with errors
```

Task file format (`tasks/todo/add-streaming-support.md`):

```markdown
# Add streaming support to A2A gateway

WebSocket streaming is needed for real-time agent responses.

## Requirements
- Support in knowledge-agent (priority)
- Backwards-compatible with HTTP polling
- Tests for streaming endpoint

## Context
- A2A protocol: docs/specs/en/a2a-protocol.md
- HTTP handler: apps/core/src/A2AGateway/A2AClient.php
```

First `# heading` = task name (for logs, branch). Entire file = full prompt for the agent.

Run:

```bash
# Sequential
./scripts/pipeline-batch.sh tasks/

# Parallel (2 workers)
./scripts/pipeline-batch.sh --workers 2 tasks/

# No arguments — defaults to tasks/ if it exists
./scripts/pipeline-batch.sh
```

Files move automatically: `todo/` → `in-progress/` → `done/` (or `failed/`).
Completed files get a metadata comment prepended:

```markdown
<!-- batch: 20260309_220000 | status: pass | duration: 1545s | branch: pipeline/add-streaming-support -->
# Add streaming support ...
```

### Option 2: Text file (simple)

```
# tasks.txt — one line = one task
Add streaming support to A2A gateway
Implement retry logic for LiteLLM client
# Lines starting with # are ignored
```

```bash
./scripts/pipeline-batch.sh tasks.txt
./scripts/pipeline-batch.sh --workers 2 --telegram tasks.txt
```

### Check results

Reports are saved to `.opencode/pipeline/reports/batch_<timestamp>.md`:

```markdown
| # | Task | Status | Duration | Branch |
|---|------|--------|----------|--------|
| 1 | Add streaming support | ✓ PASS | 1545s | `pipeline/add-streaming-support` |
| 2 | Implement retry logic | ✗ FAIL | 890s  | `pipeline/implement-retry-logic` |
```

## Parallel Execution

### How `--workers N` works

```
tasks.txt (10 tasks)
    │
    ├── worker-1 (git worktree) ──→ Task 1 ──→ Task 3 ──→ Task 5 ──→ ...
    │
    └── worker-2 (git worktree) ──→ Task 2 ──→ Task 4 ──→ Task 6 ──→ ...
```

Each worker runs in an isolated git worktree — a separate copy of the working tree sharing the same `.git` database. This allows parallel branch creation and commits without conflicts.

When a worker finishes a task, it picks up the next one from the queue. If one task is slow and another is fast, workers self-balance automatically.

### Choosing the number of workers

The worker count depends on API provider rate limits:

| Provider | RPM Limit | Recommended Workers |
|----------|-----------|---------------------|
| Claude (subscription) | 40–80 RPM | 2–3 |
| OpenRouter Free | 20 RPM | 1 |
| OpenRouter Paid | 200+ RPM | 3–5 |
| Codex | 60 RPM | 2–3 |

Practical tip: start with `--workers 2`, watch for 429 errors in logs. If none appear, scale up.

### `--stop-on-failure` in parallel mode

With `--workers > 1`, `--stop-on-failure` is ignored — all tasks run to completion. Atomically stopping parallel workers is complex, and for overnight runs it's better to execute as many tasks as possible.

## Overnight Runs

### Via `nohup` + `tmux`

```bash
# Option 1: nohup (simplest)
nohup ./scripts/pipeline-batch.sh --workers 2 --telegram tasks.txt \
  > batch.log 2>&1 &

# Option 2: tmux (can attach later to watch)
tmux new-session -d -s pipeline \
  './scripts/pipeline-batch.sh --workers 2 --telegram tasks.txt'
# Then attach: tmux attach -t pipeline
```

### Via cron

```bash
# Daily at 10 PM
0 22 * * * cd /path/to/repo && ./scripts/pipeline-batch.sh \
  --workers 2 --telegram --no-stop-on-failure tasks.txt \
  >> /var/log/pipeline-batch.log 2>&1
```

### Telegram notifications

Add `--telegram` to receive Telegram notifications at each stage:
- Start/completion of each agent
- Final batch summary

Required environment variables: `PIPELINE_TELEGRAM_BOT_TOKEN` and `PIPELINE_TELEGRAM_CHAT_ID`.

### Monitoring via Dev Reporter

When dev-reporter-agent is running, each pipeline automatically sends results via A2A. View history and stats at:

```
http://localhost:8087/admin/pipeline
```

## Model Fallback

The pipeline uses a cascading fallback system:

```
Subscriptions (Claude, Codex)    ← already paid, used first
    ↓ on 429/error
Free tier (free models)          ← no additional cost
    ↓ on 429/error
Cheap per-token (cheap tier)     ← last resort, minimal price
```

Fallbacks are configured via environment variables in `pipeline.sh`:

```bash
FALLBACK_ARCHITECT="claude-sonnet,gpt-5.3-codex,free,cheap"
FALLBACK_CODER="gpt-5.3-codex,claude-opus,free,cheap"
```

## Options

| Option | Description |
|--------|-------------|
| `--workers N` | Number of parallel workers (default: 1) |
| `--no-stop-on-failure` | Continue after failure (sequential mode) |
| `--skip-architect` | Skip the architect stage |
| `--from <agent>` | Start from a specific agent |
| `--only <agent>` | Run only a specific agent |
| `--audit` | Add quality audit at the end |
| `--telegram` | Telegram notifications |
| `--webhook <url>` | Webhook notifications |

## Examples

### 10 tasks overnight with 2 workers

```bash
nohup ./scripts/pipeline-batch.sh \
  --workers 2 \
  --telegram \
  --no-stop-on-failure \
  tasks.txt > batch.log 2>&1 &
```

### Code + tests only (no architect)

```bash
./scripts/pipeline-batch.sh --workers 3 --skip-architect tasks.txt
```

### Resume from tester for all tasks

```bash
./scripts/pipeline-batch.sh --from tester tasks.txt
```

## Reports

Each batch run generates a report:

```
.opencode/pipeline/reports/batch_20260308_220000.md
```

Report structure:

```markdown
# Batch Pipeline Results
- Started: 2026-03-08 22:00:00
- Total tasks: 10
- Workers: 2

| # | Task | Status | Duration | Branch |
|---|------|--------|----------|--------|
| 1 | ... | ✓ PASS | 1234s | ... |

## Summary
- Passed: 8/10
- Failed: 2/10
- Workers: 2
- Total duration: 14400s (240 min)
```

Individual task logs are saved to `.opencode/pipeline/logs/`.
