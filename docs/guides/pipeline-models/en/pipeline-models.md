# Pipeline Model Routing Policy

## Rules

### Provider priority

1. **Direct providers** — anthropic, openai, google, minimax (direct API key)
2. **OpenCode Zen** (`opencode/*`) / **OpenCode Go** (`opencode-go/*`)
3. **OpenRouter** — **ONLY free models** (`:free` suffix)

### Tier policy

- `tier1` — long-horizon, hardest agentic work
- `tier2` — strong balanced workhorse
- `fast` — low-latency, high-volume checks
- `free` — OpenCode Zen free + OpenRouter free last-resort continuity

Primary models should be intentionally spread across providers. This reduces the chance that one provider's short-term rate limits stall the whole pipeline.
Google exception: avoid Gemini as primary for long-running, high-fanout phases, but use it as primary for one-shot phases where it is especially strong: writing and analysis.

### Restrictions

- **NEVER** use `openrouter/anthropic/*`, `openrouter/openai/*` — paid models through middleman
- **NEVER** use `openrouter/google/*` when direct `google/*` is available
- OpenRouter = only community and open-source models with `:free` suffix

### 6-provider rule

Agents should draw from this core provider set:

| # | Provider | Example models |
|---|----------|----------------|
| 1 | **anthropic** | `claude-opus-4-6`, `claude-sonnet-4-6` |
| 2 | **openai** | `gpt-5.4`, `gpt-5.3-codex`, `gpt-5.2` |
| 3 | **google** | `gemini-3.1-pro-preview`, `gemini-3-flash-preview`, `gemini-3.1-flash-lite-preview` |
| 4 | **minimax** | `MiniMax-M2.7`, `MiniMax-M2.7-highspeed`, `MiniMax-M2.5-highspeed` |
| 5 | **opencode-go** | `glm-5`, `kimi-k2.5` |
| 6 | **opencode** (Zen) | `big-pickle`, `gpt-5-nano`, `minimax-m2.5-free` |
| 7 | **openrouter** (:free) | `openrouter/free`, `deepseek-r1-0528:free`, `qwen3-coder:free` |

### Role tiers

| Role | Primary | Fallback (openai → google → minimax → zen → openrouter:free) |
|------|---------|--------------------------------------------------------------|
| Sisyphus | `opencode-go/glm-5` | claude-opus-4-6, gpt-5.4, M2.7, big-pickle, gemini-3.1-pro-preview, openrouter/free |
| Architect | `anthropic/claude-opus-4-6` | gpt-5.4, glm-5, M2.7, gemini-3.1-pro-preview, big-pickle, openrouter/free |
| Coder | `anthropic/claude-sonnet-4-6` | M2.7, gpt-5.3-codex, glm-5, gemini-3.1-pro-preview, big-pickle, qwen3-coder:free |
| Reviewer | `minimax/MiniMax-M2.7` | gpt-5.4, glm-5, big-pickle, gemini-3.1-pro-preview, qwen3-coder:free |
| Tester | `opencode-go/kimi-k2.5` | gpt-5.3-codex, M2.7-highspeed, big-pickle, gemini-3.1-pro-preview, qwen3-coder:free |
| Auditor | `anthropic/claude-opus-4-6` | gpt-5.4, glm-5, M2.7, big-pickle, gemini-3.1-pro-preview, openrouter/free |
| Validator | `minimax/MiniMax-M2.5-highspeed` | gpt-5.2, kimi-k2.5, minimax-m2.5-free, gemini-3.1-flash-lite-preview, deepseek-r1-qwen3-8b:free |
| Documenter | `openai/gpt-5.4` | claude-sonnet-4-6, gemini-3-flash-preview, M2.5, kimi-k2.5, big-pickle, openrouter/free |
| Summarizer | `openai/gpt-5.4` | claude-opus-4-6, gemini-3.1-pro-preview, M2.7, glm-5, big-pickle, deepseek-r1-0528:free |

## Ultraworks Agent Matrix

| Agent | Workflow | Primary | Fallback 1 | Fallback 2 | Fallback 3 | Purpose |
|------|----------|---------|------------|------------|------------|---------|
| `sisyphus` | `Ultraworks only` | `opencode-go/glm-5` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `minimax/MiniMax-M2.7` | full automatic pipeline orchestration |
| `s-architect` | `Ultraworks` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | OpenSpec, architecture, technical planning |
| `s-coder` | `Ultraworks` | `anthropic/claude-sonnet-4-6` | `minimax/MiniMax-M2.7` | `openai/gpt-5.3-codex` | `opencode-go/glm-5` | primary code implementation |
| `s-reviewer` | `Ultraworks only` | `minimax/MiniMax-M2.7` | `openai/gpt-5.4` | `opencode-go/glm-5` | `opencode/big-pickle` | safe refactor, SOLID/DRY/KISS improvements |
| `s-validator` | `Ultraworks` | `minimax/MiniMax-M2.5-highspeed` | `openai/gpt-5.2` | `opencode-go/kimi-k2.5` | `opencode/minimax-m2.5-free` | static analysis, CS/PHPStan, auto-fix |
| `s-tester` | `Ultraworks` | `opencode-go/kimi-k2.5` | `openai/gpt-5.3-codex` | `minimax/MiniMax-M2.7-highspeed` | `opencode/big-pickle` | tests, test fixes, CUJ/E2E reasoning |
| `s-auditor` | `Ultraworks` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | audit, compliance, quality gate |
| `s-documenter` | `Ultraworks` | `openai/gpt-5.4` | `anthropic/claude-sonnet-4-6` | `google/gemini-3-flash-preview` | `minimax/MiniMax-M2.5` | documentation updates |
| `s-summarizer` | `Ultraworks` | `openai/gpt-5.4` | `anthropic/claude-opus-4-6` | `google/gemini-3.1-pro-preview` | `minimax/MiniMax-M2.7` | final analysis and summary |

## Builder Agent Matrix

| Agent | Workflow | Primary | Fallback 1 | Fallback 2 | Fallback 3 | Purpose |
|------|----------|---------|------------|------------|------------|---------|
| `planner` | `Builder only` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | task analysis and profile selection |
| `architect` | `Builder` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | OpenSpec, architecture, technical planning |
| `coder` | `Builder` | `anthropic/claude-sonnet-4-6` | `minimax/MiniMax-M2.7` | `openai/gpt-5.3-codex` | `opencode-go/glm-5` | primary code implementation |
| `validator` | `Builder` | `minimax/MiniMax-M2.5-highspeed` | `openai/gpt-5.2` | `opencode-go/kimi-k2.5` | `opencode/minimax-m2.5-free` | static analysis, CS/PHPStan, auto-fix |
| `tester` | `Builder` | `opencode-go/kimi-k2.5` | `openai/gpt-5.3-codex` | `minimax/MiniMax-M2.7-highspeed` | `opencode/big-pickle` | tests, test fixes, CUJ/E2E reasoning |
| `auditor` | `Builder` | `anthropic/claude-opus-4-6` | `openai/gpt-5.4` | `opencode-go/glm-5` | `minimax/MiniMax-M2.7` | audit, compliance, quality gate |
| `documenter` | `Builder` | `openai/gpt-5.4` | `anthropic/claude-sonnet-4-6` | `google/gemini-3-flash-preview` | `minimax/MiniMax-M2.5` | documentation updates |
| `summarizer` | `Builder` | `openai/gpt-5.4` | `anthropic/claude-opus-4-6` | `google/gemini-3.1-pro-preview` | `minimax/MiniMax-M2.7` | final analysis and summary |

## Configuration

### oh-my-opencode.jsonc

Sisyphus pipeline subagent config. Each subagent has:
- `model` — primary model
- `fallback_models` — ordered fallback array, typically 5 entries to cover the remaining providers

### ultraworks-monitor.sh

`_detect_model()` function picks the best available model for the Sisyphus orchestrator when launching via `opencode run`.

### Checking available models

```bash
# All models
opencode models

# Free openrouter only
opencode models | grep ':free'

# Direct providers
opencode models | grep -v '^openrouter/'
```

## Adding a new model

1. Check availability: `opencode models | grep "model-name"`
2. Determine tier by quality
3. Add to `oh-my-opencode.jsonc` for appropriate agents
4. Add to `_detect_model()` in `ultraworks-monitor.sh` if orchestrator-level model
5. OpenRouter — only if it has `:free` suffix
