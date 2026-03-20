---
name: builder-agent
description: >
  Delegate a coding task to the autonomous pipeline. Supports two workflows:
  - **Builder** (Claude Code): Creates task file in builder/tasks/todo/ — pipeline monitor auto-starts workers.
  - **Ultraworks** (OpenCode): Launches OpenCode in tmux with /auto command — Sisyphus orchestrates automatically.
  Triggers on: "builder", "delegate", "делегувати", "білдер", "ultraworks", "ultrawork", "ulw",
  "queue task", "add to pipeline", "schedule task", "pipeline task", "agent builder",
  "поставити задачу", "в чергу", "на білдера", "auto", "/auto".
  Do NOT execute the task yourself — only delegate to the appropriate pipeline.
---

# Pipeline Agent — Universal Skill

> **IMPORTANT — Sisyphus Orchestrator Exception:**
> If you are the Sisyphus orchestrator (running via `/auto` or `ultrawork` command in OpenCode),
> do NOT use this skill to delegate. You ARE the orchestrator.
> Instead, use `delegate_task()` with `s-*` subagents (s-architect, s-coder, s-validator, s-tester, s-auditor, s-documenter, s-summarizer).
> Read `.opencode/pipeline/handoff.md` for context. Follow the pipeline phases from your `prompt_append`.
> This skill is only for external delegation FROM Claude Code or FROM a user prompt — not for self-delegation within a running pipeline.

This skill delegates tasks to the autonomous multi-agent pipeline. It detects which environment you're using
and routes to the appropriate workflow.

## Quick Reference

| Environment | Workflow | Delegate Command | Monitor Command |
|-------------|----------|------------------|-----------------|
| Claude Code | Builder | Creates `builder/tasks/todo/*.md` | `make monitor-builder` |
| OpenCode | Ultraworks | Runs `/auto <task>` in tmux | `make monitor-ultraworks` |

---

## Workflow Detection

**Claude Code detected** → Use Builder workflow (task queue + monitor)
**OpenCode detected** → Use Ultraworks workflow (Sisyphus orchestration)

> Note: If you're in Claude Code but want to use Ultraworks, we can still launch OpenCode in tmux.
> See "Ultraworks from Claude Code" section below.

---

## Workflow 1: Builder (Claude Code)

**How it works:**
1. Create task file in `builder/tasks/todo/`
2. Pipeline monitor auto-starts workers
3. Workers run: Architect → Coder → Validator → Tester → Auditor → Summarizer
4. Results appear in `builder/tasks/summary/`

### Step 1 — Gather Task Details

From the user's request, determine:

1. **Title** — short imperative sentence (e.g., "Implement change: add-delivery-channels")
2. **Description** — what needs to be done (1-3 sentences)
3. **OpenSpec reference** — if this implements an OpenSpec change, link to proposal
4. **Context** — dependencies, patterns to follow, key decisions
5. **Key files** — files to create or modify
6. **Validation** — how to verify success
7. **Priority** — default 1; higher = picked up first

### Step 2 — Create the Task File

First, ensure the directory exists: `mkdir -p builder/tasks/todo`
Then write the file to `builder/tasks/todo/`.

**File format:**

```markdown
<!-- priority: 1 -->
# Task title here

Brief description of what needs to be done.

## OpenSpec

- Proposal: openspec/changes/<id>/proposal.md
- Tasks: openspec/changes/<id>/tasks.md
- Spec delta: openspec/changes/<id>/specs/<component>/spec.md

## Context

Background, dependencies, patterns to follow.

## Key files to create/update

- path/to/file.php (new or modify)

## Validation

- PHPStan level 8 passes
- CS-Fixer passes
- Unit/functional tests pass
```

### Step 3 — Verify Worker Starts

```bash
# Check if monitor is running
ps aux | grep pipeline-monitor | grep -v grep

# If not running, start it:
./builder/monitor/pipeline-monitor.sh

# Verify task moved to in-progress
ls -la builder/tasks/in-progress/
```

### Step 4 — User Report (Builder)

**Always include monitoring instructions:**

```
✅ **Builder Task Queued**

- **Task file**: builder/tasks/todo/<slug>.md
- **Priority**: N
- **Workflow**: Builder (Claude Code pipeline)

**To monitor progress:**
  make monitor-builder

**Results location:**
  - Branch: pipeline/<slug>
  - Summary: builder/tasks/summary/<timestamp>-<slug>.md
  - Reports: .opencode/pipeline/reports/
  - Logs: .opencode/pipeline/logs/
```

---

## Workflow 2: Ultraworks (OpenCode)

**How it works:**
1. Sisyphus receives task via `/auto` command
2. Orchestrates s-* subagents in parallel
3. Phases: spec → code → validate ∥ test → audit loop → docs ∥ summary
4. Results in `.opencode/pipeline/handoff.md` and git commits

### From OpenCode

Simply run:
```
/auto <task description>
```
Or use shortcut: `ultrawork`

### From Claude Code (launching Ultraworks)

If you want to use Ultraworks from Claude Code:

1. Create task file for reference:
```bash
mkdir -p .opencode/pipeline
echo '{"profile":"standard","reasoning":"Task from Claude Code","agents":["coder","validator","tester","summarizer"]}' > .opencode/pipeline/plan.json
```

2. Launch OpenCode in tmux:
```bash
./builder/monitor/ultraworks-monitor.sh launch "<task description>"
```

3. Attach to monitor:
```bash
make monitor-ultraworks
# or
tmux attach -t ultraworks
```

### User Report (Ultraworks)

**Always include monitoring instructions:**

```
✅ **Ultraworks Task Queued**

- **Workflow**: Ultraworks (Sisyphus pipeline)
- **Session**: tmux session 'ultraworks'

**To monitor progress:**
  make monitor-ultraworks

**To attach to OpenCode:**
  tmux attach -t ultraworks

**Results location:**
  - Handoff: .opencode/pipeline/handoff.md
  - Reports: .opencode/pipeline/reports/
  - Plan: .opencode/pipeline/plan.json
```

---

## Monitoring Commands

### Builder Monitor

```bash
make monitor-builder
# or directly:
./builder/monitor/pipeline-monitor.sh
```

Features:
- Real-time task progress
- Worker status
- Cost tracking
- Keys: `[s]` start, `[k]` kill, `[f]` retry failed, `[+/-]` priority

### Ultraworks Monitor

```bash
make monitor-ultraworks
# Shows:
# - Current phase
# - Handoff state
# - Recent reports
# - Interactive menu
```

Actions available:
1. Show current state
2. Launch OpenCode (tmux)
3. View latest report
4. View handoff
5. Tail logs

---

## Choosing Between Workflows

| Use Builder when... | Use Ultraworks when... |
|---------------------|------------------------|
| You're in Claude Code | You're in OpenCode |
| You want task queue | You want automatic execution |
| You want manual control | You want parallel phases |
| You need to prioritize tasks | You want fastest execution |

---

## Priority & Task Ordering (Builder only)

Priority controls pick-up order: `<!-- priority: N -->` in task file.
- Higher number = picked up first
- Default = 1

**Dependencies:**
- Higher priority for dependency task
- For parallel work: `export MONITOR_WORKERS=2`

---

## Troubleshooting

### Builder Issues

**Monitor not running:**
```bash
./builder/monitor/pipeline-monitor.sh
```

**Task not picked up:**
```bash
ls builder/tasks/todo/
ls builder/tasks/in-progress/
```

**Worker limit reached:**
```bash
export MONITOR_WORKERS=2
./builder/monitor/pipeline-monitor.sh
```

### Ultraworks Issues

**Session not found:**
```bash
./builder/monitor/ultraworks-monitor.sh launch "<task>"
```

**Pipeline stuck:**
```bash
cat .opencode/pipeline/handoff.md
ls -lt .opencode/pipeline/reports/
```

**Resume from state:**
- In OpenCode: `/finish`

---

## Important Rules

1. **NEVER execute the task yourself** — only delegate
2. **Detect environment**: Claude Code → Builder, OpenCode → Ultraworks
3. **Always include monitoring command** in user report
4. For Builder: Include `## Validation` section in task file
5. Use Ukrainian in descriptions if user writes in Ukrainian