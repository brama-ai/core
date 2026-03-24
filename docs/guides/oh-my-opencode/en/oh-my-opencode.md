# Oh My OpenCode (OmO) — Multi-Model Agent Orchestration

## Overview

[Oh My OpenCode](https://github.com/code-yeongyu/oh-my-openagent) (OmO) is an open-source plugin for OpenCode that orchestrates multiple AI models through specialized discipline agents. We adopted it because our builder pipeline arrived at the same multi-agent concept independently, but OmO implements it more maturely.

OmO provides Sisyphus — an orchestrator that delegates to subagents in parallel, with fallback chains across providers. Combined with our OpenSpec-driven pipeline agents, this gives the platform two complementary workflows:

1. **Sequential pipeline** (`/pipeline`) — manual agent switching, human controls each phase
2. **Sisyphus pipeline** (`/auto`, `ultrawork`) — fully automatic, parallel execution

Both workflows now follow the same unified role contract:
- `planner` and `summarizer` are the only roles that may read `handoff.md` by default
- all other roles consume `CONTEXT` passed from the caller/orchestrator as the primary source of truth
- Builder and Ultraworks may still use different runtime agent names today, but they should implement the same unified `u-*` role semantics

## Architecture

```
.opencode/
├── oh-my-opencode.jsonc          # OmO config: agents, categories, fallback, concurrency
├── opencode.json                 # OpenCode config: LSP (PHP Intelephense)
│
├── skills/                       # Shared knowledge (both workflows)
│   ├── coding/SKILL.md           #   Tech stack, per-app make targets
│   ├── testing/SKILL.md          #   Codeception/pytest patterns, coverage
│   ├── validation/SKILL.md       #   PHPStan, CS-fixer targets
│   ├── auditing/SKILL.md         #   S/T/C/X/O/D checklist, severity
│   ├── openspec/SKILL.md         #   Spec format, proposal scaffold
│   └── documentation/SKILL.md    #   Bilingual patterns, INDEX.md rules
│
├── agents/                       # All agents
│   ├── planner.md                #   Pipeline: analyzes task → plan.json
│   ├── architect.md              #   Pipeline: OpenSpec proposals
│   ├── coder.md                  #   Pipeline: implements code
│   ├── validator.md              #   Pipeline: PHPStan + CS fix
│   ├── tester.md                 #   Pipeline: tests + coverage
│   ├── auditor.md                #   Pipeline: audit + fix
│   ├── documenter.md             #   Pipeline: bilingual docs
│   ├── summarizer.md             #   Pipeline: final summary
│   ├── s-architect.md            #   Sisyphus: specs (delegated)
│   ├── s-coder.md                #   Sisyphus: code (delegated)
│   ├── s-reviewer.md             #   Legacy/optional improvement pass, not the default happy path
│   ├── s-validator.md            #   Sisyphus: lint (parallel)
│   ├── s-tester.md               #   Sisyphus: tests (parallel)
│   ├── s-auditor.md              #   Sisyphus: audit (read-only)
│   ├── s-documenter.md           #   Sisyphus: docs (parallel)
│   └── s-summarizer.md           #   Sisyphus: summary (parallel)
│
├── commands/                     # Slash commands
│   ├── auto.md                   #   /auto — full Sisyphus pipeline
│   ├── implement.md              #   /implement — skip architect
│   ├── validate.md               #   /validate — quality gate only
│   ├── audit.md                  #   /audit — audit + remediation context only
│   ├── finish.md                 #   /finish — resume from state
│   └── pipeline.md               #   /pipeline — manual sequential
│
└── pipeline/                     # Runtime artifacts
    ├── handoff.md                #   Shared bus between agents
    └── reports/                  #   Audit reports per run
```

## Setup

### Devcontainer (automatic)

Everything installs automatically:
- **Dockerfile**: `tmux`, `intelephense` (PHP LSP)
- **post-create.sh**: `bunx oh-my-opencode install --no-tui --claude=max5`

### Manual

```bash
bunx oh-my-opencode install          # interactive TUI
npm install -g intelephense          # PHP LSP for agents
```

### Verify

```bash
opencode --version                                    # 1.0.150+
cat ~/.config/opencode/opencode.json                  # "oh-my-opencode" in plugins
intelephense --version                                # PHP LSP active
printenv | grep -E 'OPENAI|ANTHROPIC|GOOGLE|MINIMAX|OPENCODE|OPENROUTER'
```

If you use direct providers in OmO routing, define their keys in your local `.env.local`. The devcontainer now forwards `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GOOGLE_API_KEY`, `MINIMAX_API_KEY`, `OPENCODE_API_KEY`, and `OPENROUTER_API_KEY` into the OpenCode process.

Recommended `.env.local` setup for devcontainer-based OpenCode:
- `OPENROUTER_API_KEY` for the default local routing path
- `OPENAI_API_KEY`, `GOOGLE_API_KEY`, `MINIMAX_API_KEY`, `OPENCODE_API_KEY` if you use these providers directly in OmO routing
- `ANTHROPIC_API_KEY` only if you explicitly use Anthropic as a raw API-key provider; if you use Anthropic via OpenCode OAuth/subscription, it appears under `Credentials`, not under `Environment`

You can verify what OpenCode sees with:

```bash
opencode auth list
```

Expected output shape:
- `Credentials` section: OAuth/subscription-backed providers
- `Environment` section: providers discovered from `.env.local` / process env

## Workflows

### 1. Sisyphus (automatic)

The primary workflow. Sisyphus orchestrates all phases with parallel execution:

```
/auto <task description>
```
or simply: `ultrawork`

```mermaid
flowchart LR
    A[Task] --> B[s-architect]
    B --> C[s-coder]
    C --> D[s-auditor]
    D --> E[s-validator]
    D --> F[s-tester]
    E --> G{s-coder follow-up needed?}
    F --> G
    G -- yes --> C
    G -- no --> H[s-documenter]
    G -- no --> I[s-summarizer]
```

**Phases:**
1. **Spec** — s-architect creates OpenSpec proposal (skipped if tasks.md exists)
2. **Implement** — s-coder writes code from specs
3. **Audit and remediation** — s-auditor performs a post-coder quality pass, applies safe in-scope fixes when possible, and emits remediation context
4. **Quality** — s-validator + s-tester run in parallel against the combined result of coder + auditor
5. **Loopback if needed** — if validator/tester expose broader implementation gaps, the remediation context is returned to s-coder for one more focused pass
6. **Finalize** — s-documenter + s-summarizer run in parallel; summarizer always writes `builder/tasks/summary/*.md`

**Shortcuts:**
| Command | What it does |
|---------|-------------|
| `ultrawork` / `ulw` | Full pipeline (phases 1-6) |
| `/implement <change-id>` | Phases 2-6 (tasks.md exists) |
| `/validate` | Phase 4 only (quality gate) |
| `/audit` | Phase 3 only (audit + remediation context) |
| `/finish` | Resume from handoff.md state |

### Common Scenarios

#### Scenario: Fix a bug with Ultraworks

Use this when you want the orchestrator to localize the bug, patch it, run the guardrails, and leave a final summary:

```text
ultrawork fix the duplicate webhook retry bug in the billing worker. reproduce it, patch only the billing worker path, run the relevant tests, and summarize any remaining follow-up.
```

Expected flow:
- `s-architect` is skipped if no spec change is needed
- `s-coder` implements the bug fix
- `s-auditor` applies safe in-scope remediation if it finds obvious gaps
- `s-validator` and `s-tester` verify the final result
- `s-summarizer` writes the task outcome and unresolved follow-ups

#### Scenario: Run E2E with a limited bug-fix budget in Ultraworks

Use this when the goal is primarily validation, but you allow the workflow to fix only a bounded number of straightforward issues before stopping:

```text
ultrawork run the checkout E2E flow for the marketplace app. you may fix at most 2 small bugs that block the scenario, then stop and summarize any remaining failures.
```

Recommended constraints to state in the prompt:
- the exact flow or CUJ to exercise
- the app or service boundary
- the maximum number of allowed fixes, for example `1` or `2`
- whether schema, API contract, or cross-service changes are out of scope

Behavior expectation:
- `s-tester` focuses on reproducing the E2E path
- `s-auditor` may apply safe local fixes inside the allowed bug budget
- if the failures require a larger redesign or spec change, the run should stop and report follow-up work instead of continuing indefinitely

### Ultraworks Stability

`Ultraworks` now runs behind a dedicated stability wrapper in [builder/monitor/ultraworks-monitor.sh](/workspaces/ai-community-platform/builder/monitor/ultraworks-monitor.sh):

- global wall-clock timeout via `ULTRAWORKS_MAX_RUNTIME` (default: `7200`)
- stall watchdog via `ULTRAWORKS_STALL_TIMEOUT` (default: `900`)
- the watchdog checks both task-log growth and `.opencode/pipeline/handoff.md` updates
- if progress stops, the wrapper terminates `opencode run`, then triggers post-mortem summary generation and summary normalization
- a failed or stalled run should still leave `builder/tasks/summary/*.md`, not just logs

Useful env vars:

```bash
ULTRAWORKS_MAX_RUNTIME=7200
ULTRAWORKS_STALL_TIMEOUT=900
ULTRAWORKS_WATCHDOG_INTERVAL=30
```

Headless example:

```bash
./builder/monitor/ultraworks-monitor.sh headless "$(cat builder/tasks/todo/my-task.md)"
```

### Ultraworks Model Table

| Agent | Workflow | Primary | Fallback 1 | Fallback 2 | Fallback 3 |
|-------|----------|---------|------------|------------|------------|
| `sisyphus` | `Ultraworks only` | `opencode-go/glm-5` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `minimax/MiniMax-M2.7` |
| `s-architect` | `Ultraworks` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` |
| `s-coder` | `Ultraworks` | `anthropic/claude-sonnet-4-6` | `minimax/MiniMax-M2.7` | `openai/gpt-5.4` | `opencode-go/glm-5` |
| `s-validator` | `Ultraworks` | `minimax/MiniMax-M2.5-highspeed` | `openai/gpt-5.4` | `opencode-go/kimi-k2.5` | `opencode/minimax-m2.5-free` |
| `s-tester` | `Ultraworks` | `opencode-go/kimi-k2.5` | `openai/gpt-5.4` | `minimax/MiniMax-M2.7-highspeed` | `opencode/big-pickle` |
| `s-auditor` | `Ultraworks` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` |
| `s-documenter` | `Ultraworks` | `openai/gpt-5.4` | `anthropic/claude-sonnet-4-6` | `google/gemini-3-flash-preview` | `minimax/MiniMax-M2.5` |
| `s-summarizer` | `Ultraworks` | `openai/gpt-5.4` | `anthropic/claude-opus-4-6` | `google/gemini-3.1-pro-preview` | `minimax/MiniMax-M2.7` |

### 2. Sequential pipeline (manual)

For when you want control over each phase:

```
/pipeline <task description>
```

Each agent runs one at a time. You switch agents manually (Tab → @agent).

## Model Strategy

Each agent uses the optimal model for its role, with automatic fallback:

| Agent | Primary Model | Purpose |
|-------|--------------|---------|
| Sisyphus | `opencode-go/glm-5` | long-horizon orchestration |
| Architect | `anthropic/claude-opus-4-6` | OpenSpec, architecture, planning |
| Coder | `anthropic/claude-sonnet-4-6` | primary code implementation |
| Validator | `minimax/MiniMax-M2.5-highspeed` | fast static-analysis loop |
| Tester | `opencode-go/kimi-k2.5` | tests, CUJ/E2E reasoning |
| Auditor | `anthropic/claude-opus-4-6` | post-coder remediation, quality gate, safe in-scope fixes |
| Security Review | `anthropic/claude-opus-4-6` | read-only security assessment, follow-up proposal/task generation |
| Documenter | `openai/gpt-5.4` | documentation writing |
| Summarizer | `openai/gpt-5.4` | final analysis + summary |

Fallback triggers automatically on rate limits (`model_fallback: true`).

## Built-in MCPs

Installed with oh-my-opencode, always on:
- **Exa** — web search
- **Context7** — official docs lookup
- **Grep.app** — GitHub code search

## LSP

PHP Intelephense configured in `.opencode/opencode.json`:
```json
{
  "lsp": {
    "php": {
      "command": ["intelephense", "--stdio"],
      "extensions": [".php"]
    }
  }
}
```

Agents get: diagnostics, go-to-definition, find references, type inference for all PHP code.

## Configuration

| File | Purpose |
|------|---------|
| `.opencode/opencode.json` | OpenCode core config (LSP, plugins) |
| `.opencode/oh-my-opencode.jsonc` | OmO config (agents, fallbacks, concurrency, tmux) |
| `~/.config/opencode/oh-my-opencode.jsonc` | Personal overrides |

## Links

- Repository: [code-yeongyu/oh-my-openagent](https://github.com/code-yeongyu/oh-my-openagent)
- Installation guide: [docs/guide/installation.md](https://github.com/code-yeongyu/oh-my-openagent/blob/dev/docs/guide/installation.md)
