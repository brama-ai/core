# Multi-Agent Pipeline

## Overview

The pipeline is an automated task execution system that runs a sequence of AI agents. Each agent has a specific role: planning, architecture, code, audit, validation, tests, documentation, and a final task summary.

```
Task → Planner → Architect → Coder → [Auditor] → Validator → Tester → [Documenter] → Summarizer
```

The pipeline automatically determines task complexity and selects the appropriate set of agents.

## Quick Start

```bash
# Single task
make pipeline TASK="Add retry logic to LiteLLM client"

# Or directly
./scripts/pipeline.sh "Add retry logic to LiteLLM client"

# With audit
./scripts/pipeline.sh --audit "Create new hello-agent"

# Batch run (separate docs: pipeline-batch.md)
make pipeline-batch FILE=tasks.txt
```

## Auto-Planning

The first step is the **Planner** agent (Sonnet, 5 min limit). It analyzes the task and generates `plan.json` with the pipeline configuration.

### Profiles

| Profile | When | Agents |
|---------|------|--------|
| `quick-fix` | Minor fixes, config, 1-3 files | coder → validator → summarizer |
| `standard` | New feature, single app, multiple files | architect → coder → validator → tester → summarizer |
| `complex` | Cross-service changes, migrations, API, new agents | architect → coder → auditor → validator → tester → summarizer |

### How Planner Decides

1. Reads the task description
2. Searches for mentioned files/patterns in code (glob/grep)
3. Checks existing OpenSpec proposals
4. Estimates file count, apps, and services affected
5. Determines if migrations or API changes are needed
6. Generates `.opencode/pipeline/plan.json`

### plan.json

```json
{
  "profile": "standard",
  "reasoning": "New feature in single app, needs spec and tests",
  "agents": ["architect", "coder", "validator", "tester", "summarizer"],
  "skip_openspec": false,
  "estimated_files": 8,
  "apps_affected": ["knowledge-agent"],
  "needs_migration": false,
  "needs_api_change": true,
  "is_agent_task": true,
  "timeout_overrides": {},
  "model_overrides": {}
}
```

### Skipping the Planner

```bash
# Specify profile manually
./scripts/pipeline.sh --profile quick-fix "Fix typo in README"

# Skip planner entirely
./scripts/pipeline.sh --skip-planner "Implement openspec change add-streaming"
```

## Agents

### Planner (5 min)
- **Model**: Sonnet 4.6
- **Role**: analyzes complexity, generates plan
- **Output**: `.opencode/pipeline/plan.json`

### Architect (45 min)
- **Model**: Opus 4.6
- **Role**: creates OpenSpec proposal (proposal, design, tasks, specs)
- **Output**: `openspec/changes/<id>/`
- **Skipped**: when the task says "Implement openspec change ..." (spec is ready)

### Coder (60 min)
- **Model**: Sonnet 4.6
- **Role**: writes code, migrations, configs
- **Input**: spec from OpenSpec or handoff.md
- **Stage gate**: verifies that coder actually modified files

### Validator (20 min)
- **Model**: Codex
- **Role**: PHPStan level 8 + CS Fixer, fixes all issues
- **Loop**: cs-fix → cs-check → analyse → repeat until zero errors

### Tester (30 min)
- **Model**: Codex
- **Role**: runs tests, writes missing ones, fixes failures
- **Targets**: Codeception (PHP), pytest (Python), convention-test

### Auditor (20 min)
- **Model**: Sonnet 4.6
- **Role**: quality and platform standards compliance check
- **Checklist**: Structure, Testing, Config, Security, Observability, Docs
- **Output**: report with PASS/WARN/FAIL verdicts
- **Activation**: `--audit` flag, `complex` profile, or automatically for agent tasks

### Documenter (15 min)
- **Model**: Sonnet 4.6
- **Role**: updates bilingual documentation (UA + EN)
- **Not needed by default**: coder writes docs from tasks.md

### Summarizer (15 min)
- **Model**: GPT-5.4
- **Role**: writes the final markdown summary for every agent that actually worked on the task
- **Output**: `task/<timestamp>-<task-slug>.md`
- **Includes**: what each agent did, difficulties, remaining fixes, and one proposed follow-up task

## Auto-Audit for Agent Tasks

When a task involves creating or modifying an agent, the pipeline **automatically injects the auditor after coder**. This works through three mechanisms:

1. **Planner**: sets `is_agent_task: true` in plan.json
2. **Keyword detection**: searches for "agent" in the task description
3. **apps_affected**: checks for apps with `-agent` suffix

```
standard + agent task:
  architect → coder → [auditor] → validator → tester → summarizer
```

The auditor checks changes against checklists:
- `checklist-php.md` — 51 checks for PHP/Symfony agents
- `checklist-python.md` — 47 checks for Python/FastAPI agents
- `checklist-platform.md` — 13 platform-wide checks

To force audit for any task:

```bash
./scripts/pipeline.sh --audit "Any task description"
```

## Handoff — Context Passing Between Agents

The file `.opencode/pipeline/handoff.md` is a shared document updated by each agent:

| Agent | What It Records |
|-------|----------------|
| Architect | change-id, apps affected, DB/API changes |
| Coder | files modified, migrations created, deviations |
| Validator | PHPStan/CS results |
| Tester | test results, new tests |
| Auditor | audit verdict, recommendations |
| Documenter | docs created, final status |
| Summarizer | summary file path, per-agent summary, next-task recommendation |

## CLI Options

| Option | Description |
|--------|-------------|
| `--skip-architect` | Skip the architect (spec already exists) |
| `--from <agent>` | Resume from a specific agent |
| `--only <agent>` | Run only one agent |
| `--branch <name>` | Custom branch name |
| `--task-file <path>` | Read task from file (for long prompts) |
| `--audit` | Add auditor agent |
| `--profile <name>` | Set profile manually |
| `--skip-planner` | Don't run the planner |
| `--no-commit` | Skip auto-commits between agents |
| `--resume` | Resume from checkpoint |
| `--telegram` | Telegram notifications |
| `--webhook <url>` | Webhook notifications |

## Monitoring

### Console Monitor

```bash
./scripts/pipeline-monitor.sh
```

Interactive TUI monitor with tabs:

| Tab | Description |
|-----|-------------|
| 1:Overview | Task status, progress bar, batch state |
| 2:Activity | Event timeline: tasks, agents, workers (time, duration, status) |
| 3+:worker-N | Per-worker logs (dynamic, appear when workers are active) |

Keyboard shortcuts:

| Key | Action |
|-----|--------|
| `←/→` | Switch tabs |
| `↑/↓` | Select task in list |
| `Enter` | View task details |
| `Esc/q` | Back / quit |
| `s` | Start batch (or extra worker for selected task) |
| `f` | Retry failed tasks |
| `k` | Kill batch |
| `l` | View logs for selected task (failed/in-progress) |
| `d` | Delete task |
| `a` | Archive completed tasks |
| `+/-` | Change priority of todo task |

Batch status shows elapsed time, PID, and worker count:
`Running (3m 42s, PID 12345, 2 workers)`

When tasks are waiting and no batch is running:
`Not running — 3 tasks waiting, press [s] to start`

### Dev Reporter Monitoring

```
http://localhost:8087/admin/pipeline
```

Each pipeline automatically sends results via A2A to dev-reporter-agent.

### Viewing Logs

```bash
# Logs for a specific run
ls .opencode/pipeline/logs/

# Format: <timestamp>_<agent>.log
cat .opencode/pipeline/logs/20260311_140000_coder.log

# Reports
ls .opencode/pipeline/reports/
```

### Telegram Notifications

```bash
export PIPELINE_TELEGRAM_BOT_TOKEN="your-bot-token"
export PIPELINE_TELEGRAM_CHAT_ID="your-chat-id"

./scripts/pipeline.sh --telegram "Task description"
```

Notifications at every stage: start, agent completion, final result.

## Tasks — How They Work

### Task Lifecycle

```
Task (text or .md file)
    ↓
pipeline.sh creates branch: pipeline/<task-slug>
    ↓
Each agent: executes → commits → checkpoint
    ↓
Result: COMPLETED or FAILED at <agent>
    ↓
Report: .opencode/pipeline/reports/<timestamp>.md
    ↓
Task summary: task/<timestamp>-<task-slug>.md
```

### Checkpoint & Resume

After each agent, a checkpoint is saved (`tasks/artifacts/<task-slug>/checkpoint.json`):

```json
{
  "task": "Add streaming support",
  "branch": "pipeline/add-streaming",
  "started": "2026-03-11 04:00:00",
  "agents": {
    "architect": {"status": "done", "duration": 180, "commit": "abc123"},
    "coder": {"status": "done", "duration": 900, "commit": "def456"},
    "validator": {"status": "failed", "duration": 120}
  }
}
```

To resume from the point of failure:

```bash
./scripts/pipeline.sh --from validator --branch pipeline/add-streaming "Add streaming support"
```

### Git Workflow

- Each task gets its own branch: `pipeline/<task-slug>`
- Each agent commits its work: `[pipeline:coder] add-streaming`
- Stage gate after coder: verifies actual code changes exist
- Migrations run after coder (if applicable)

## Model Fallback

Cascading fallback system on errors (429, timeout):

```
Subscriptions (Claude, Codex)     ← already paid, used first
    ↓ error
Free tier                          ← no additional cost
    ↓ error
Paid per-token (cheap tier)        ← last resort, minimal cost
```

Configuration via environment variables:

```bash
PIPELINE_FALLBACK_ARCHITECT="claude-sonnet,gpt-5.3-codex,free,cheap"
PIPELINE_FALLBACK_CODER="gpt-5.3-codex,claude-opus,free,cheap"
```

## Timeouts

| Agent | Default | Variable |
|-------|---------|----------|
| Planner | 5 min | `PIPELINE_TIMEOUT_PLANNER` |
| Architect | 45 min | `PIPELINE_TIMEOUT_ARCHITECT` |
| Coder | 60 min | `PIPELINE_TIMEOUT_CODER` |
| Validator | 20 min | `PIPELINE_TIMEOUT_VALIDATOR` |
| Tester | 30 min | `PIPELINE_TIMEOUT_TESTER` |
| Auditor | 20 min | `PIPELINE_TIMEOUT_AUDITOR` |
| Documenter | 15 min | `PIPELINE_TIMEOUT_DOCUMENTER` |

## Token & Cost Budgets

```bash
# Total cost limit (USD)
PIPELINE_MAX_COST=5.00

# Per-agent token limits
PIPELINE_TOKEN_BUDGET_CODER=2000000
PIPELINE_TOKEN_BUDGET_ARCHITECT=500000
```

## Examples

### Quick Fix

```bash
./scripts/pipeline.sh "Fix typo in hello-agent README"
# Planner → quick-fix: coder → validator
```

### New Feature in a Single App

```bash
./scripts/pipeline.sh "Add health check endpoint to knowledge-agent"
# Planner → standard: architect → coder → validator → tester
```

### New Agent (auto-audit)

```bash
./scripts/pipeline.sh "Create new summarizer-agent with FastAPI"
# Planner → standard + is_agent_task: architect → coder → auditor → validator → tester
```

### Complex Cross-Service Change

```bash
./scripts/pipeline.sh "Add centralized session management across all agents"
# Planner → complex: architect → coder → validator → tester → auditor
```

### Resume After Failure

```bash
./scripts/pipeline.sh --from tester --branch pipeline/add-health-check \
  "Add health check endpoint to knowledge-agent"
```

### Run a Single Agent

```bash
./scripts/pipeline.sh --only validator "Run PHPStan on core"
```

## File Structure

```
scripts/
├── pipeline.sh              # Main orchestrator
├── pipeline-batch.sh        # Batch runner
├── pipeline-run-task.sh     # Single task in worktree
├── pipeline-monitor.sh      # Interactive TUI monitor
└── pipeline-stats.sh        # Statistics

.opencode/
├── pipeline/
│   ├── profiles.json        # Profiles (quick-fix, standard, complex)
│   ├── handoff.md           # Inter-agent context
│   ├── plan.json            # Planner output (runtime)
│   ├── tasks.example.txt    # Example task file
│   ├── logs/                # Agent logs
│   └── reports/             # Run reports
└── agents/
    ├── planner.md           # Planner prompt
    ├── architect.md         # Architect prompt
    ├── coder.md             # Coder prompt
    ├── validator.md         # Validator prompt
    ├── tester.md            # Tester prompt
    └── documenter.md        # Documenter prompt
```
