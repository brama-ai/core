# Oh My OpenCode — Quick Start

## Prerequisites

Devcontainer auto-installs everything. If manual:
```bash
npm install -g opencode-ai intelephense
bunx oh-my-opencode install --no-tui --claude=max5 --gemini=no --copilot=no
make builder-setup
```

Verify: `opencode --version` (1.0.150+)

---

## Scenario 1: Simple Task (one phrase)

You have a quick fix or small feature. Just type it in OpenCode:

```
ultrawork Fix the health endpoint to return proper Content-Type header in hello-agent
```

**What happens:**
1. Sisyphus reads handoff.md, decides to skip architect (no spec needed)
2. Delegates s-coder → writes the fix
3. Delegates s-validator + s-tester in parallel → lint + tests
4. Delegates s-summarizer → writes summary

**Result:** Branch `pipeline/fix-hello-health-content-type`, summary in `builder/tasks/summary/`.

### Other quick examples

```
ultrawork Add missing PHPStan return types in knowledge-agent
```
```
ultrawork Update the admin dashboard sidebar to include coder link
```
```
/validate                    # just run lint + tests on current changes
```

---

## Scenario 2: OpenSpec Proposal (planned feature)

You have a real feature that needs spec-first design. Two-step process:

### Step 1 — Create the proposal

In OpenCode, switch to architect agent or use Sisyphus:

```
/auto Create OpenSpec proposal for adding webhook notifications to agent lifecycle events
```

Sisyphus delegates s-architect who creates:
```
openspec/changes/add-webhook-notifications/
├── proposal.md          # what and why
├── design.md            # architecture, trade-offs
├── tasks.md             # ordered work items with checkboxes
└── specs/
    └── webhooks/spec.md # spec deltas with scenarios
```

### Step 2 — Implement

Review the proposal, then:

```
/implement add-webhook-notifications
```

This skips Phase 1 (architect) and runs Phases 2-5:
coder → validator ∥ tester → audit loop (if agent) → docs ∥ summary

### Step 3 — Resume if interrupted

If something failed or was interrupted:

```
/finish
```

Reads handoff.md, determines what's done, runs only remaining phases.

---

## Scenario 3: Batch Tasks Overnight

Queue multiple tasks and let the monitor run them while you sleep.

### Option A: Via Claude (recommended)

Tell Claude to delegate:

```
Делегуй білдеру:
1. Implement change add-deep-crawling
2. Implement change add-delivery-channels
3. Fix PHPStan errors in dev-reporter-agent
```

Claude creates 3 task files in `builder/tasks/todo/` with priorities:
```
builder/tasks/todo/implement-change-add-deep-crawling.md        <!-- priority: 3 -->
builder/tasks/todo/implement-change-add-delivery-channels.md    <!-- priority: 2 -->
builder/tasks/todo/fix-phpstan-dev-reporter.md                  <!-- priority: 1 -->
```

### Option B: Manual task files

```bash
mkdir -p builder/tasks/todo

cat > builder/tasks/todo/implement-deep-crawling.md << 'EOF'
<!-- priority: 3 -->
# Implement change: add-deep-crawling

Реалізувати deep crawling для knowledge-agent.

## OpenSpec

- Proposal: openspec/changes/add-deep-crawling/proposal.md
- Tasks: openspec/changes/add-deep-crawling/tasks.md

## Validation

- PHPStan level 8 passes
- make knowledge-test passes
- make conventions-test passes
EOF
```

### Start the monitor

```bash
./builder/monitor/pipeline-monitor.sh
```

Tasks execute in priority order (highest first). Monitor auto-picks next task when current finishes.

### Check results in the morning

```bash
# Overview
ls builder/tasks/done/
ls builder/tasks/failed/

# Summaries (Ukrainian, with cost/time per agent)
cat builder/tasks/summary/*.md

# Diffs
git log --oneline --all | grep pipeline/
git diff main...pipeline/implement-deep-crawling
```

---

## Scenario 4: Two Parallel Tasks via tmux

Run two independent tasks simultaneously in separate tmux panes.

### Setup

```bash
# Create tmux session with two panes
tmux new-session -d -s pipeline
tmux split-window -h -t pipeline
```

### Start tasks in parallel

```bash
# Left pane: knowledge-agent feature
tmux send-keys -t pipeline:0.0 'cd /workspaces/ai-community-platform && opencode' Enter
# Wait for OpenCode to start, then:
tmux send-keys -t pipeline:0.0 'ultrawork Implement change add-deep-crawling' Enter

# Right pane: core admin feature
tmux send-keys -t pipeline:0.1 'cd /workspaces/ai-community-platform && opencode' Enter
tmux send-keys -t pipeline:0.1 'ultrawork Implement change add-tenant-management' Enter
```

### Attach and watch

```bash
tmux attach -t pipeline
```

Navigate: `Ctrl-b ←` / `Ctrl-b →` to switch panes.

### Alternative: Use builder monitor with MONITOR_WORKERS=2

If you prefer the builder pipeline (bash-based, not OpenCode):

```bash
# Queue both tasks
cat > builder/tasks/todo/task-a.md << 'EOF'
<!-- priority: 2 -->
# Implement change: add-deep-crawling
...
EOF

cat > builder/tasks/todo/task-b.md << 'EOF'
<!-- priority: 2 -->
# Implement change: add-tenant-management
...
EOF

# Start monitor with 2 workers
MONITOR_WORKERS=2 ./builder/monitor/pipeline-monitor.sh
```

Both tasks run in parallel on separate git worktrees.

---

## Quick Reference

| Want to... | Command |
|-----------|---------|
| Run full pipeline (automatic) | `ultrawork <task>` or `/auto <task>` |
| Implement existing spec | `/implement <change-id>` |
| Run lint + tests only | `/validate` |
| Run audit only | `/audit` |
| Resume interrupted work | `/finish` |
| Manual agent-by-agent | `/pipeline <task>` |
| Queue task for builder | Tell Claude: "делегуй білдеру" |
| Batch overnight | Create files in `builder/tasks/todo/`, run monitor |
| Parallel via tmux | Two OpenCode instances in tmux panes |
| Parallel via monitor | `MONITOR_WORKERS=2 ./builder/monitor/pipeline-monitor.sh` |

## Where to Find Results

| Artifact | Location |
|----------|----------|
| Agent handoff | `.opencode/pipeline/handoff.md` |
| Audit reports | `.opencode/pipeline/reports/*_audit.md` |
| Pipeline plan | `pipeline-plan.json` |
| Task summaries | `builder/tasks/summary/<timestamp>-<slug>.md` |
| Git branches | `pipeline/<slug>` |
| Batch reports | `.opencode/pipeline/reports/batch_*.md` |
| Agent logs | `.opencode/pipeline/logs/*.meta.json` |
