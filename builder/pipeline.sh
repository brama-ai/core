#!/usr/bin/env bash
#
# Multi-agent pipeline orchestrator for OpenCode.
#
# Runs pipeline agents in sequence:
#   1. Architect (Opus)      — creates OpenSpec proposal
#   2. Coder (Sonnet)        — implements the code
#   3. Auditor (optional)    — audits agent/platform compliance
#   4. Validator (Codex)     — runs PHPStan + CS, fixes issues
#   5. Tester (Codex)        — runs tests, fixes failures
#   6. Documenter (optional) — writes documentation
#   7. Summarizer (GPT-5.4)  — writes final task summary to tasks/summary/*.md
#
# Usage:
#   ./scripts/pipeline.sh "Add streaming support to A2A gateway"
#   ./scripts/pipeline.sh --skip-architect "implement change add-a2a-streaming"
#   ./scripts/pipeline.sh --from coder "Continue implementing add-a2a-streaming"
#   ./scripts/pipeline.sh --only validator "Run PHPStan on core"
#   ./scripts/pipeline.sh --audit "Add feature X"
#   ./scripts/pipeline.sh --webhook https://hooks.slack.com/... "Task"
#
set -euo pipefail

# Allow override via env (used by pipeline-batch.sh worktree mode)
REPO_ROOT="${PIPELINE_REPO_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
PIPELINE_DIR="$REPO_ROOT/.opencode/pipeline"
LOG_DIR="$PIPELINE_DIR/logs"
REPORT_DIR="$PIPELINE_DIR/reports"
HANDOFF_FILE="$PIPELINE_DIR/handoff.md"
HANDOFF_TEMPLATE="$PIPELINE_DIR/handoff-template.md"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m'

# ── Event log (live activity feed for monitor) ────────────────────────
EVENT_LOG="$PIPELINE_DIR/events.log"

emit_event() {
  local event_type="$1"; shift
  local details="$*"
  local ts; ts=$(date '+%H:%M:%S')
  local epoch; epoch=$(date +%s)
  echo "${epoch}|${ts}|${event_type}|${details}" >> "$EVENT_LOG" 2>/dev/null || true
}

# Agent order
AGENTS=(architect coder validator tester summarizer)

# Timeouts per agent (seconds, override via env)
PIPELINE_TIMEOUT_ARCHITECT="${PIPELINE_TIMEOUT_ARCHITECT:-2700}"   # 45 min
PIPELINE_TIMEOUT_CODER="${PIPELINE_TIMEOUT_CODER:-3600}"           # 60 min
PIPELINE_TIMEOUT_VALIDATOR="${PIPELINE_TIMEOUT_VALIDATOR:-1200}"   # 20 min
PIPELINE_TIMEOUT_TESTER="${PIPELINE_TIMEOUT_TESTER:-1800}"         # 30 min
PIPELINE_TIMEOUT_DOCUMENTER="${PIPELINE_TIMEOUT_DOCUMENTER:-900}"  # 15 min
PIPELINE_TIMEOUT_AUDITOR="${PIPELINE_TIMEOUT_AUDITOR:-1200}"       # 20 min
PIPELINE_TIMEOUT_SUMMARIZER="${PIPELINE_TIMEOUT_SUMMARIZER:-900}"  # 15 min

# Retry config
MAX_RETRIES="${PIPELINE_MAX_RETRIES:-2}"
RETRY_DELAY="${PIPELINE_RETRY_DELAY:-30}"

# "cheap" virtual model — paid models under $1/1M tokens
# Override via: PIPELINE_CHEAP_MODELS="model1,model2"
CHEAP_MODELS="${PIPELINE_CHEAP_MODELS:-openrouter/deepseek-v3.2,openrouter/gemini-3.1-flash-lite}"

# "free" virtual model — expands to a chain of free models
# Override via: PIPELINE_FREE_MODELS="model1,model2,model3"
FREE_MODELS="${PIPELINE_FREE_MODELS:-opencode/big-pickle,opencode/gpt-5-nano,opencode/minimax-m2.5-free}"

# Fallback model chains (override via env: PIPELINE_FALLBACK_ARCHITECT="model1,model2")
# Tiers: subscriptions (Claude+Codex) → free (OpenRouter) → cheap (paid per-token)
# Subscriptions already paid (flat rate), free costs nothing, cheap is last resort
FALLBACK_ARCHITECT="${PIPELINE_FALLBACK_ARCHITECT:-anthropic/claude-sonnet-4-20250514,openai/gpt-5.3-codex,free,cheap}"
FALLBACK_CODER="${PIPELINE_FALLBACK_CODER:-openai/gpt-5.3-codex,anthropic/claude-opus-4-20250514,free,cheap}"
FALLBACK_VALIDATOR="${PIPELINE_FALLBACK_VALIDATOR:-anthropic/claude-sonnet-4-20250514,openai/codex-mini-latest,free,cheap}"
FALLBACK_TESTER="${PIPELINE_FALLBACK_TESTER:-anthropic/claude-sonnet-4-20250514,openai/codex-mini-latest,free,cheap}"
FALLBACK_DOCUMENTER="${PIPELINE_FALLBACK_DOCUMENTER:-anthropic/claude-opus-4-20250514,free,cheap}"
FALLBACK_AUDITOR="${PIPELINE_FALLBACK_AUDITOR:-anthropic/claude-sonnet-4-20250514,free,cheap}"
FALLBACK_SUMMARIZER="${PIPELINE_FALLBACK_SUMMARIZER:-openai/gpt-5.4,openai/gpt-5.3-codex,free,cheap}"

# ── Help ──────────────────────────────────────────────────────────────

show_help() {
  cat << 'HELP'
Usage: ./scripts/pipeline.sh [options] "task description"
       ./scripts/pipeline.sh [options] --task-file path/to/task.md

Options:
  --skip-architect    Skip the architect stage (use existing spec)
  --from <agent>      Start from a specific agent
  --only <agent>      Run only a specific agent
  --branch <name>     Use specific branch name (default: auto-generated)
  --task-file <path>  Read task prompt from a file instead of CLI argument
                      If file is in tasks/todo/, it moves through
                      todo/ → in-progress/ → done/ (or failed/)
                      so pipeline-monitor.sh can track progress
  --audit             Add auditor quality gate agent
  --webhook <url>     POST JSON summary to webhook on completion/failure
  --telegram          Send status updates via Telegram bot
  --no-commit         Skip auto-commits between agents
  --profile <name>    Use task profile: quick-fix, standard, complex
  --skip-planner      Skip the planner agent (use default pipeline)
  -h, --help          Show this help

Agents: planner, architect, coder, auditor, validator, tester, documenter, summarizer

Profiles:
  quick-fix    — coder + validator + summarizer
  standard     — architect + coder + validator + tester + summarizer
  complex      — standard + auditor + extended timeouts

Timeouts (override via env):
  PIPELINE_TIMEOUT_PLANNER=300     (5 min)
  PIPELINE_TIMEOUT_ARCHITECT=2700  (45 min)
  PIPELINE_TIMEOUT_CODER=3600     (60 min)
  PIPELINE_TIMEOUT_VALIDATOR=1200 (20 min)
  PIPELINE_TIMEOUT_TESTER=1800   (30 min)
  PIPELINE_TIMEOUT_DOCUMENTER=900 (15 min)
  PIPELINE_TIMEOUT_SUMMARIZER=900 (15 min)
  PIPELINE_MAX_RETRIES=2

Token budgets (override via env, 0=unlimited):
  PIPELINE_TOKEN_BUDGET_PLANNER=100000
  PIPELINE_TOKEN_BUDGET_ARCHITECT=500000
  PIPELINE_TOKEN_BUDGET_CODER=2000000
  PIPELINE_TOKEN_BUDGET_SUMMARIZER=300000
  PIPELINE_MAX_COST=<max total cost in USD>
  PIPELINE_RETRY_DELAY=30

Telegram (env vars):
  PIPELINE_TELEGRAM_BOT_TOKEN    Bot API token
  PIPELINE_TELEGRAM_CHAT_ID      Chat/group ID to post to

Fallback models (env vars, comma-separated):
  Tiers: subscriptions (Claude+Codex) → free → cheap (per-token)
  PIPELINE_FALLBACK_ARCHITECT    (default: sonnet,codex,free,cheap)
  PIPELINE_FALLBACK_CODER        (default: codex,opus,free,cheap)
  PIPELINE_FALLBACK_VALIDATOR    (default: sonnet,codex-mini,free,cheap)
  PIPELINE_FALLBACK_TESTER       (default: sonnet,codex-mini,free,cheap)
  PIPELINE_FALLBACK_DOCUMENTER   (default: opus,free,cheap)
  PIPELINE_FALLBACK_SUMMARIZER   (default: gpt-5.4,gpt-5.3-codex,free,cheap)
  PIPELINE_CHEAP_MODELS          (default: deepseek-v3.2,gemini-3.1-flash-lite)
  PIPELINE_FREE_MODELS           (default: big-pickle,gpt-5-nano,minimax-m2.5-free)

Examples:
  ./scripts/pipeline.sh "Add streaming support to A2A"
  ./scripts/pipeline.sh --skip-architect "Implement openspec change add-a2a-streaming"
  ./scripts/pipeline.sh --from validator "Fix validation issues"
  ./scripts/pipeline.sh --only tester "Run tests for hello-agent"
  ./scripts/pipeline.sh --audit "Add feature with quality gate"
  ./scripts/pipeline.sh --only summarizer --task-file tasks/done/example.md
HELP
}

# Parse arguments
SKIP_ARCHITECT=false
FROM_AGENT=""
ONLY_AGENT=""
BRANCH_NAME=""
TASK_MESSAGE=""
TASK_FILE=""
WEBHOOK_URL=""
ENABLE_AUDIT=false
NO_COMMIT=false
RESUME_MODE=false
TELEGRAM_NOTIFY=false
TELEGRAM_BOT_TOKEN="${PIPELINE_TELEGRAM_BOT_TOKEN:-}"
TELEGRAM_CHAT_ID="${PIPELINE_TELEGRAM_CHAT_ID:-}"
PIPELINE_PROFILE=""
SKIP_PLANNER=false

# Pipeline cost budget (empty = unlimited)
PIPELINE_MAX_COST="${PIPELINE_MAX_COST:-}"

# Per-agent token tracking via temp files (bash 3.x has no associative arrays)
AGENT_TOKENS_DIR="/tmp/pipeline_tokens_$$"
mkdir -p "$AGENT_TOKENS_DIR"
set_agent_tokens() { echo "$2" > "$AGENT_TOKENS_DIR/$1"; }
get_agent_tokens() { cat "$AGENT_TOKENS_DIR/$1" 2>/dev/null || echo "{}"; }

# Per-agent token budgets (0 = unlimited)
PIPELINE_TOKEN_BUDGET_PLANNER="${PIPELINE_TOKEN_BUDGET_PLANNER:-100000}"
PIPELINE_TOKEN_BUDGET_ARCHITECT="${PIPELINE_TOKEN_BUDGET_ARCHITECT:-500000}"
PIPELINE_TOKEN_BUDGET_CODER="${PIPELINE_TOKEN_BUDGET_CODER:-2000000}"
PIPELINE_TOKEN_BUDGET_VALIDATOR="${PIPELINE_TOKEN_BUDGET_VALIDATOR:-500000}"
PIPELINE_TOKEN_BUDGET_TESTER="${PIPELINE_TOKEN_BUDGET_TESTER:-500000}"
PIPELINE_TOKEN_BUDGET_DOCUMENTER="${PIPELINE_TOKEN_BUDGET_DOCUMENTER:-300000}"
PIPELINE_TOKEN_BUDGET_AUDITOR="${PIPELINE_TOKEN_BUDGET_AUDITOR:-300000}"
PIPELINE_TOKEN_BUDGET_SUMMARIZER="${PIPELINE_TOKEN_BUDGET_SUMMARIZER:-300000}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --skip-architect)
      SKIP_ARCHITECT=true
      shift
      ;;
    --from)
      FROM_AGENT="$2"
      shift 2
      ;;
    --only)
      ONLY_AGENT="$2"
      shift 2
      ;;
    --branch)
      BRANCH_NAME="$2"
      shift 2
      ;;
    --webhook)
      WEBHOOK_URL="$2"
      shift 2
      ;;
    --audit)
      ENABLE_AUDIT=true
      shift
      ;;
    --task-file)
      TASK_FILE="$2"
      shift 2
      ;;
    --resume)
      RESUME_MODE=true
      shift
      ;;
    --no-commit)
      NO_COMMIT=true
      shift
      ;;
    --telegram)
      TELEGRAM_NOTIFY=true
      shift
      ;;
    --profile)
      PIPELINE_PROFILE="$2"
      shift 2
      ;;
    --skip-planner)
      SKIP_PLANNER=true
      shift
      ;;
    --help|-h)
      show_help
      exit 0
      ;;
    *)
      TASK_MESSAGE="$1"
      shift
      ;;
  esac
done

# Load task from file if --task-file was specified
if [[ -n "$TASK_FILE" ]]; then
  if [[ ! -f "$TASK_FILE" ]]; then
    echo -e "${RED}Error: Task file not found: ${TASK_FILE}${NC}"
    exit 1
  fi
  TASK_MESSAGE=$(cat "$TASK_FILE")
fi

if [[ -z "$TASK_MESSAGE" ]]; then
  show_help
  exit 1
fi

# ── Task file lifecycle (tasks/ directory integration) ──────────────
# When --task-file points to a file in tasks/todo/, manage its lifecycle
# so pipeline-monitor.sh can track progress in real time.
TASK_LIFECYCLE=false
TASK_ACTIVE_FILE=""
TASKS_DIR=""

_detect_task_lifecycle() {
  local abs_tasks_dir="$REPO_ROOT/builder/tasks"

  if [[ -n "$TASK_FILE" ]]; then
    # --task-file mode: check if file is inside tasks/todo/
    local abs_task
    abs_task=$(cd "$(dirname "$TASK_FILE")" && pwd)/$(basename "$TASK_FILE")
    if [[ "$abs_task" == "$abs_tasks_dir/todo/"* ]]; then
      TASK_LIFECYCLE=true
      TASKS_DIR="$abs_tasks_dir"
    fi
  else
    # Text mode: auto-create task file in tasks/todo/ so monitor can track it
    local slug
    slug=$(echo "$TASK_MESSAGE" | grep -m1 '[^ ]' | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g' | sed 's/--*/-/g' | sed 's/^-//' | sed 's/-$//' | cut -c1-50)
    [[ -z "$slug" ]] && slug="task-$(date +%s)"
    local auto_task_file="$abs_tasks_dir/todo/${slug}.md"
    if [[ -d "$abs_tasks_dir/todo" ]]; then
      mkdir -p "$abs_tasks_dir/todo"
      # Write the first line as title, rest as body
      local title
      title=$(echo "$TASK_MESSAGE" | head -1)
      if [[ "$title" != "# "* ]]; then
        echo "# ${title}" > "$auto_task_file"
        echo "" >> "$auto_task_file"
        echo "$TASK_MESSAGE" >> "$auto_task_file"
      else
        echo "$TASK_MESSAGE" > "$auto_task_file"
      fi
      TASK_FILE="$auto_task_file"
      TASK_LIFECYCLE=true
      TASKS_DIR="$abs_tasks_dir"
    fi
  fi
}

_task_move_to_in_progress() {
  [[ "$TASK_LIFECYCLE" != true ]] && return
  local src="$TASK_FILE"
  local dest="${TASKS_DIR}/in-progress/$(basename "$src")"
  mkdir -p "${TASKS_DIR}/in-progress"
  mv "$src" "$dest"
  TASK_ACTIVE_FILE="$dest"
  echo -e "${BLUE}Task moved: todo/ → in-progress/${NC}"
}

_task_move_to_done() {
  [[ "$TASK_LIFECYCLE" != true ]] && return
  local src="${TASK_ACTIVE_FILE:-$TASK_FILE}"
  [[ ! -f "$src" ]] && return
  local branch="${1:-}"
  local duration="${2:-0}"
  local dest="${TASKS_DIR}/done/$(basename "$src")"
  mkdir -p "${TASKS_DIR}/done"
  {
    echo "<!-- batch: ${TIMESTAMP} | status: pass | duration: ${duration}s | branch: ${branch} -->"
    cat "$src"
  } > "$dest"
  rm -f "$src"
  echo -e "${GREEN}Task moved: in-progress/ → done/${NC}"
}

_task_move_to_failed() {
  [[ "$TASK_LIFECYCLE" != true ]] && return
  local src="${TASK_ACTIVE_FILE:-$TASK_FILE}"
  [[ ! -f "$src" ]] && return
  local branch="${1:-}"
  local duration="${2:-0}"
  local dest="${TASKS_DIR}/failed/$(basename "$src")"
  mkdir -p "${TASKS_DIR}/failed"
  {
    echo "<!-- batch: ${TIMESTAMP} | status: fail | duration: ${duration}s | branch: ${branch} -->"
    cat "$src"
  } > "$dest"
  rm -f "$src"
  echo -e "${RED}Task moved: in-progress/ → failed/${NC}"
}

_detect_task_lifecycle

_is_autotest_task() {
  [[ "$TASK_MESSAGE" == *"<!-- source: autotest -->"* || "$TASK_MESSAGE" == *$'\n# Admin created task '* || "$TASK_MESSAGE" == "# Admin created task "* ]]
}

_skip_autotest_task() {
  echo -e "${YELLOW}Autotest task detected — skipping pipeline and marking as done.${NC}"
  _task_move_to_in_progress
  _task_move_to_done "autotest/skipped" "0"
  exit 0
}

if _is_autotest_task; then
  _skip_autotest_task
fi

# ── Pre-flight checks ────────────────────────────────────────────────

preflight() {
  echo -e "${BOLD}Pre-flight checks...${NC}"
  local errors=0

  # 1. opencode CLI
  if ! command -v opencode &>/dev/null; then
    echo -e "  ${RED}✗ opencode CLI not found${NC}"
    errors=$((errors + 1))
  else
    echo -e "  ${GREEN}✓ opencode $(opencode --version 2>/dev/null)${NC}"
  fi

  # 2. Docker daemon (optional - only required for tests)
  if ! command -v docker &>/dev/null; then
    echo -e "  ${YELLOW}⚠ Docker not installed (tests will be skipped)${NC}"
  elif ! docker info &>/dev/null; then
    echo -e "  ${YELLOW}⚠ Docker daemon not running (tests will be skipped)${NC}"
  else
    echo -e "  ${GREEN}✓ Docker daemon${NC}"
  fi

  # 3. Key services (postgres, redis)
  if docker info &>/dev/null; then
    if docker compose -f "$REPO_ROOT/compose.yaml" ps --format json 2>/dev/null | grep -q '"running"'; then
      echo -e "  ${GREEN}✓ Docker stack running${NC}"

      # Postgres connectivity
      if docker compose -f "$REPO_ROOT/compose.yaml" exec -T postgres pg_isready -q 2>/dev/null; then
        echo -e "  ${GREEN}✓ Postgres accepting connections${NC}"
      else
        echo -e "  ${YELLOW}⚠ Postgres not ready (tests may fail)${NC}"
      fi
    else
      echo -e "  ${YELLOW}⚠ Docker stack may not be running (run 'make up' first)${NC}"
    fi
  fi

  # 4. Git state
  if ! git -C "$REPO_ROOT" rev-parse --git-dir &>/dev/null; then
    echo -e "  ${RED}✗ Not a git repository${NC}"
    errors=$((errors + 1))
  else
    if [[ -n "$(git -C "$REPO_ROOT" status --porcelain 2>/dev/null)" ]]; then
      echo -e "  ${YELLOW}⚠ Git working tree has uncommitted changes${NC}"
      # Block if openspec/ files have uncommitted changes (P1 sync guard)
      local openspec_dirty
      openspec_dirty=$(git -C "$REPO_ROOT" status --porcelain -- openspec/ 2>/dev/null || true)
      if [[ -n "$openspec_dirty" ]]; then
        echo -e "  ${RED}✗ openspec/ has uncommitted changes — commit them first${NC}"
        echo -e "  ${RED}  Pipeline branches from HEAD; uncommitted specs won't be included${NC}"
        echo "$openspec_dirty" | while IFS= read -r line; do
          echo -e "    ${RED}${line}${NC}"
        done
        errors=$((errors + 1))
      fi
    else
      echo -e "  ${GREEN}✓ Git working tree clean${NC}"
    fi
  fi

  # 5. Required tools
  for cmd in timeout jq; do
    if command -v "$cmd" &>/dev/null; then
      echo -e "  ${GREEN}✓ ${cmd}${NC}"
    else
      echo -e "  ${YELLOW}⚠ ${cmd} not found (some features disabled)${NC}"
    fi
  done

  echo ""

  if [[ $errors -gt 0 ]]; then
    echo -e "${RED}Pre-flight failed with ${errors} error(s). Aborting.${NC}"
    exit 1
  fi
}

# ── Setup directories ─────────────────────────────────────────────────

mkdir -p "$LOG_DIR" "$REPORT_DIR"

# ── Determine which agents to run ─────────────────────────────────────

get_agents_to_run() {
  local agent_list=("${AGENTS[@]}")

  # Add auditor if enabled and not already present. Keep it after coder.
  if [[ "$ENABLE_AUDIT" == true ]]; then
    local has_auditor=false
    local new_list=()
    for agent in "${agent_list[@]}"; do
      [[ "$agent" == "auditor" ]] && has_auditor=true
    done
    if [[ "$has_auditor" != true ]]; then
      for agent in "${agent_list[@]}"; do
        new_list+=("$agent")
        if [[ "$agent" == "coder" ]]; then
          new_list+=("auditor")
        fi
      done
      agent_list=("${new_list[@]}")
    fi
  fi

  if [[ -n "$ONLY_AGENT" ]]; then
    echo "$ONLY_AGENT"
    return
  fi

  local has_summarizer=false
  for agent in "${agent_list[@]}"; do
    [[ "$agent" == "summarizer" ]] && has_summarizer=true
  done
  if [[ "$has_summarizer" != true ]]; then
    agent_list+=("summarizer")
  fi

  local started=false
  for agent in "${agent_list[@]}"; do
    if [[ -n "$FROM_AGENT" ]]; then
      if [[ "$agent" == "$FROM_AGENT" ]]; then
        started=true
      fi
      if ! $started; then
        continue
      fi
    fi

    if [[ "$SKIP_ARCHITECT" == true && "$agent" == "architect" ]]; then
      continue
    fi

    echo "$agent"
  done
}

# ── Git branch setup ──────────────────────────────────────────────────

setup_branch() {
  if [[ -n "$BRANCH_NAME" ]]; then
    echo "$BRANCH_NAME"
  else
    local slug
    slug=$(_task_slug "$TASK_MESSAGE")
    echo "pipeline/${slug}"
  fi
}

# ── Get timeout for agent ─────────────────────────────────────────────

get_timeout() {
  local agent="$1"
  local var_name="PIPELINE_TIMEOUT_$(echo "$agent" | tr '[:lower:]' '[:upper:]')"
  echo "${!var_name:-1800}"
}

# ── Commit agent work ─────────────────────────────────────────────────

commit_agent_work() {
  local agent="$1"
  local task_slug="$2"

  if [[ "$NO_COMMIT" == true ]]; then
    return 0
  fi

  # Check if there are changes to commit
  if git -C "$REPO_ROOT" diff --quiet && git -C "$REPO_ROOT" diff --cached --quiet && [[ -z "$(git -C "$REPO_ROOT" ls-files --others --exclude-standard)" ]]; then
    echo -e "  ${BLUE}No changes to commit after ${agent}${NC}"
    return 0
  fi

  git -C "$REPO_ROOT" add -A
  local commit_msg="[pipeline:${agent}] ${task_slug}"

  if git -C "$REPO_ROOT" commit -m "$commit_msg" --no-verify 2>/dev/null; then
    local hash
    hash=$(git -C "$REPO_ROOT" rev-parse --short HEAD)
    echo -e "  ${GREEN}✓ Committed: ${hash} — ${commit_msg}${NC}"

    # Update handoff with commit hash
    if [[ -f "$HANDOFF_FILE" ]]; then
      # Append commit info to the agent's section
      echo "- **Commit (${agent})**: ${hash}" >> "$HANDOFF_FILE"
    fi
    return 0
  else
    echo -e "  ${YELLOW}⚠ Commit failed (may have no changes)${NC}"
    return 0
  fi
}

# ── Checkpoint & Artifacts ────────────────────────────────────────────
#
# Each task gets an artifacts directory: tasks/artifacts/<task-slug>/
# Inside:
#   checkpoint.json  — tracks which agents completed successfully
#   <agent>/         — agent-specific artifacts (logs, proposals, etc.)
#
# checkpoint.json format:
# {
#   "task": "...", "branch": "...", "started": "...",
#   "agents": {
#     "architect": {"status":"done","duration":123,"commit":"abc1234"},
#     "coder": {"status":"done","duration":456,"commit":"def5678"}
#   }
# }

ARTIFACTS_BASE="$REPO_ROOT/builder/tasks/artifacts"

# Generate slug from task message (first # title line only)
_task_slug() {
  local text="$1"
  # Extract first # heading as title
  local title
  title=$(echo "$text" | grep -m1 '^# ' | sed 's/^# //')
  if [[ -z "$title" ]]; then
    # Fallback: first non-empty line
    title=$(echo "$text" | grep -m1 '[^ ]')
  fi
  local slug
  slug=$(echo "$title" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g' | sed 's/--*/-/g' | sed 's/^-//' | sed 's/-$//' | cut -c1-50)
  # If slug is empty (non-ASCII title), use task file basename
  if [[ -z "$slug" && -n "$TASK_FILE" ]]; then
    slug=$(basename "$TASK_FILE" .md)
  fi
  # Ultimate fallback
  if [[ -z "$slug" ]]; then
    slug="task-$(date +%s)"
  fi
  echo "$slug"
}

# Initialize artifacts directory for a task
init_artifacts() {
  local slug="$1"
  local branch="$2"
  ARTIFACTS_DIR="$ARTIFACTS_BASE/$slug"
  CHECKPOINT_FILE="$ARTIFACTS_DIR/checkpoint.json"
  TASK_SUMMARY_DIR="$REPO_ROOT/builder/tasks/summary"
  TASK_SUMMARY_FILE="$TASK_SUMMARY_DIR/${TIMESTAMP}-${slug}.md"

  mkdir -p "$ARTIFACTS_DIR"
  mkdir -p "$TASK_SUMMARY_DIR"

  # Only create new checkpoint if not resuming
  if [[ "$RESUME_MODE" == true && -f "$CHECKPOINT_FILE" ]]; then
    echo -e "${BLUE}Resuming from checkpoint: ${CHECKPOINT_FILE}${NC}"
    return
  fi

  # Create fresh checkpoint
  cat > "$CHECKPOINT_FILE" << CHECKPOINT_EOF
{
  "task": $(printf '%s' "$TASK_MESSAGE" | python3 -c 'import sys,json; print(json.dumps(sys.stdin.read()))'),
  "branch": "$branch",
  "started": "$(date '+%Y-%m-%d %H:%M:%S')",
  "agents": {}
}
CHECKPOINT_EOF
}

set_planned_agents() {
  [[ -f "$CHECKPOINT_FILE" ]] || return 0

  python3 - "$CHECKPOINT_FILE" "$@" <<'PYEOF'
import json, sys
cp_file = sys.argv[1]
planned = sys.argv[2:]
with open(cp_file, 'r') as f:
    data = json.load(f)
data['planned_agents'] = planned
with open(cp_file, 'w') as f:
    json.dump(data, f, indent=2)
PYEOF
}

# Write checkpoint after agent completes
write_checkpoint() {
  local agent="$1"
  local status="$2"
  local duration="$3"
  local commit_hash="${4:-}"
  local tokens_json="${5:-{}}"

  [[ -f "$CHECKPOINT_FILE" ]] || return 0

  # Use python3 to safely update JSON (stdin to avoid shell quoting issues)
  python3 - "$CHECKPOINT_FILE" "$agent" "$status" "$duration" "$commit_hash" "$tokens_json" <<'PYEOF'
import json, sys
cp_file, agent, status, duration, commit, tokens_raw = sys.argv[1:7]
with open(cp_file, 'r') as f:
    data = json.load(f)
try:
    tokens = json.loads(tokens_raw) if tokens_raw != '{}' else {}
except json.JSONDecodeError:
    tokens = {}
from datetime import datetime
data['agents'][agent] = {
    'status': status,
    'duration': int(duration),
    'commit': commit,
    'finished': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
    'tokens': tokens
}
with open(cp_file, 'w') as f:
    json.dump(data, f, indent=2)
PYEOF
  if [[ $? -ne 0 ]]; then
    echo -e "  ${YELLOW}⚠ write_checkpoint failed for ${agent}${NC}" >&2
  fi
}

# Copy agent log to artifacts
save_agent_artifact() {
  local agent="$1"
  local log_file="$2"

  [[ -d "$ARTIFACTS_DIR" ]] || return 0

  local agent_dir="$ARTIFACTS_DIR/$agent"
  mkdir -p "$agent_dir"

  # Copy log file
  if [[ -f "$log_file" ]]; then
    cp "$log_file" "$agent_dir/$(basename "$log_file")"
  fi

  # Copy agent-created files (e.g., openspec proposals for architect)
  if [[ "$agent" == "architect" ]]; then
    # Copy any new openspec changes
    local changes_dir="$REPO_ROOT/openspec/changes"
    if [[ -d "$changes_dir" ]]; then
      for d in "$changes_dir"/*/; do
        [[ -d "$d" ]] || continue
        # Only copy recently modified proposals (last 30 min)
        if find "$d" -maxdepth 0 -mmin -30 -print -quit 2>/dev/null | grep -q .; then
          cp -r "$d" "$agent_dir/" 2>/dev/null || true
        fi
      done
    fi
  elif [[ "$agent" == "summarizer" && -f "$TASK_SUMMARY_FILE" ]]; then
    cp "$TASK_SUMMARY_FILE" "$agent_dir/$(basename "$TASK_SUMMARY_FILE")" 2>/dev/null || true
  fi
}

# Read checkpoint and determine which agent to resume from
get_resume_agent() {
  [[ -f "$CHECKPOINT_FILE" ]] || return

  python3 - "$CHECKPOINT_FILE" <<'PYEOF' 2>/dev/null || true
import json, sys
with open(sys.argv[1], 'r') as f:
    data = json.load(f)
agents_order = data.get('planned_agents') or ['planner', 'architect', 'coder', 'auditor', 'validator', 'tester', 'documenter', 'summarizer']
completed = data.get('agents', {})
for agent in agents_order:
    info = completed.get(agent, {})
    if info.get('status') != 'done':
        print(agent)
        break
PYEOF
}

# Print checkpoint summary
print_checkpoint_summary() {
  [[ -f "$CHECKPOINT_FILE" ]] || return

  python3 - "$CHECKPOINT_FILE" <<'PYEOF' 2>/dev/null || true
import json, sys
with open(sys.argv[1], 'r') as f:
    data = json.load(f)
agents = data.get('agents', {})
if not agents:
    print('  (no completed agents)')
    exit()
for name, info in agents.items():
    status = info.get('status', '?')
    dur = info.get('duration', 0)
    commit = info.get('commit', '')
    icon = '✓' if status == 'done' else '✗'
    print(f'  {icon} {name}: {status} ({dur}s) {commit}')
PYEOF
}

# ── Dev Reporter Agent integration ───────────────────────────────────

send_report_to_agent() {
  local status="$1"
  local failed_agent="${2:-}"
  local total_duration="$3"

  local core_url="${PLATFORM_CORE_URL:-http://localhost:80}"

  local failed_agent_json="null"
  if [[ -n "$failed_agent" ]]; then
    failed_agent_json="\"${failed_agent}\""
  fi

  local payload
  payload=$(printf '{"intent":"devreporter.ingest","agent":"dev-reporter-agent","payload":{"pipeline_id":"%s","task":"%s","branch":"%s","status":"%s","failed_agent":%s,"duration_seconds":%s,"agent_results":[]}}' \
    "${TIMESTAMP}" \
    "${TASK_MESSAGE//\"/\\\"}" \
    "${branch//\"/\\\"}" \
    "${status}" \
    "${failed_agent_json}" \
    "${total_duration}")

  local response
  response=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "${core_url}/api/v1/a2a/send-message" \
    -H "Content-Type: application/json" \
    -d "$payload" \
    --max-time 10 2>/dev/null) || response="000"

  if [[ "$response" == "200" || "$response" == "201" ]]; then
    echo -e "  ${GREEN}✓ Pipeline report sent to dev-reporter-agent${NC}"
  else
    echo -e "  ${YELLOW}⚠ Could not reach dev-reporter-agent (HTTP ${response}) — continuing${NC}"
  fi
}

# ── Telegram notifications ────────────────────────────────────────────

send_telegram() {
  if [[ "$TELEGRAM_NOTIFY" != true ]]; then
    return 0
  fi

  if [[ -z "$TELEGRAM_BOT_TOKEN" || -z "$TELEGRAM_CHAT_ID" ]]; then
    echo -e "  ${YELLOW}⚠ Telegram: missing PIPELINE_TELEGRAM_BOT_TOKEN or PIPELINE_TELEGRAM_CHAT_ID${NC}"
    return 0
  fi

  local message="$1"
  curl -s -X POST "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
    -d chat_id="$TELEGRAM_CHAT_ID" \
    -d parse_mode="HTML" \
    -d text="$message" \
    -d disable_web_page_preview=true \
    &>/dev/null || echo -e "  ${YELLOW}⚠ Telegram notification failed${NC}"
}

# ── Run migrations if needed ──────────────────────────────────────────

run_migrations() {
  echo -e "  ${BLUE}Checking for new migrations...${NC}"

  local has_migrations=false

  # Check for PHP migrations (Doctrine)
  if git -C "$REPO_ROOT" diff --name-only HEAD~1 2>/dev/null | grep -qE 'migrations/Version'; then
    has_migrations=true

    # Determine which app has migrations
    if git -C "$REPO_ROOT" diff --name-only HEAD~1 | grep -q 'apps/core/migrations'; then
      echo -e "  ${CYAN}Running core migrations...${NC}"
      (cd "$REPO_ROOT" && make migrate 2>&1) || echo -e "  ${YELLOW}⚠ Core migration warning${NC}"
    fi
    if git -C "$REPO_ROOT" diff --name-only HEAD~1 | grep -q 'apps/knowledge-agent/migrations'; then
      echo -e "  ${CYAN}Running knowledge-agent migrations...${NC}"
      (cd "$REPO_ROOT" && make knowledge-migrate 2>&1) || echo -e "  ${YELLOW}⚠ Knowledge migration warning${NC}"
    fi
    if git -C "$REPO_ROOT" diff --name-only HEAD~1 | grep -q 'apps/dev-reporter-agent/migrations'; then
      echo -e "  ${CYAN}Running dev-reporter-agent migrations...${NC}"
      (cd "$REPO_ROOT" && make dev-reporter-migrate 2>&1) || echo -e "  ${YELLOW}⚠ Dev-reporter migration warning${NC}"
    fi
  fi

  # Check for Python migrations (Alembic)
  if git -C "$REPO_ROOT" diff --name-only HEAD~1 2>/dev/null | grep -qE 'alembic/versions'; then
    has_migrations=true
    echo -e "  ${CYAN}Running news-maker migrations...${NC}"
    (cd "$REPO_ROOT" && make news-migrate 2>&1) || echo -e "  ${YELLOW}⚠ News-maker migration warning${NC}"
  fi

  if ! $has_migrations; then
    echo -e "  ${BLUE}No new migrations detected${NC}"
  fi
}

# ── Model fallback ────────────────────────────────────────────────────

get_fallback_chain() {
  local agent="$1"
  local var_name="FALLBACK_$(echo "$agent" | tr '[:lower:]' '[:upper:]')"
  local chain="${!var_name:-}"

  # Expand "cheap" placeholder into cheap models chain
  if [[ -n "$chain" && "$chain" == *"cheap"* ]]; then
    chain=$(echo "$chain" | sed "s|cheap|${CHEAP_MODELS}|g")
  fi

  # Expand "free" placeholder into the actual free models chain
  if [[ -n "$chain" && "$chain" == *"free"* ]]; then
    chain=$(echo "$chain" | sed "s|free|${FREE_MODELS}|g")
  fi

  echo "$chain"
}

is_rate_limit_error() {
  local log_file="$1"
  grep -qiE 'rate.?limit|429|quota|too many requests|capacity|overloaded' "$log_file" 2>/dev/null
}

get_current_model() {
  local agent="$1"
  local agent_file="$REPO_ROOT/.opencode/agents/${agent}.md"
  sed -n 's/^model:[[:space:]]*//p' "$agent_file" 2>/dev/null | tr -d ' '
}

swap_agent_model() {
  local agent="$1"
  local new_model="$2"
  local agent_file="$REPO_ROOT/.opencode/agents/${agent}.md"

  if [[ ! -f "$agent_file" ]]; then
    return 1
  fi

  local old_model
  old_model=$(get_current_model "$agent")

  sed -i.bak "s|^model:.*|model: ${new_model}|" "$agent_file"
  rm -f "${agent_file}.bak"

  echo -e "  ${YELLOW}⚡ Model swap: ${old_model} → ${new_model}${NC}"
  send_telegram "⚡ <b>${agent}</b> model swap
<code>${old_model}</code> → <code>${new_model}</code>
📋 <i>Rate limit hit, using fallback</i>"
}

restore_agent_model() {
  local agent="$1"
  local original_model="$2"
  local agent_file="$REPO_ROOT/.opencode/agents/${agent}.md"

  if [[ -n "$original_model" && -f "$agent_file" ]]; then
    sed -i.bak "s|^model:.*|model: ${original_model}|" "$agent_file"
    rm -f "${agent_file}.bak"
  fi
}

# ── Token tracking via opencode session export ───────────────────────

# Get the most recent opencode session ID for the current working directory
get_latest_session_id() {
  local session_json
  session_json=$(cd "$REPO_ROOT" && opencode session list --format json -n 1 2>/dev/null) || session_json=""

  if [[ -z "$session_json" ]]; then
    echo ""
    return
  fi

  echo "$session_json" | jq -r '.[0].id // empty' 2>/dev/null || echo ""
}

# Query token usage from an opencode session export
# Replaces the old LiteLLM-based query_agent_tokens()
query_session_tokens() {
  local session_id="$1"
  local fallback='{"input_tokens":0,"output_tokens":0,"cache_read":0,"cache_write":0,"cost":0}'

  if [[ -z "$session_id" ]]; then
    echo "$fallback"
    return
  fi

  local tmp_file="/tmp/pipeline_session_${$}_${RANDOM}.json"

  # Export to temp file — piping truncates large sessions
  if ! opencode export "$session_id" > "$tmp_file" 2>/dev/null; then
    rm -f "$tmp_file"
    echo "$fallback"
    return
  fi

  # Aggregate tokens across all messages
  local result
  result=$(jq '{
    input_tokens: [.messages[].info.tokens.input // 0] | add,
    output_tokens: [.messages[].info.tokens.output // 0] | add,
    cache_read: [.messages[].info.tokens.cache.read // 0] | add,
    cache_write: [.messages[].info.tokens.cache.write // 0] | add,
    cost: 0
  }' "$tmp_file" 2>/dev/null) || result=""

  rm -f "$tmp_file"

  if [[ -z "$result" ]]; then
    echo "$fallback"
    return
  fi

  echo "$result"
}

# Detect worker ID from worktree path (e.g. .pipeline-worktrees/worker-2 → worker-2)
_detect_worker_id() {
  local cwd; cwd=$(pwd)
  if [[ "$cwd" =~ \.pipeline-worktrees/(worker-[0-9]+) ]]; then
    echo "${BASH_REMATCH[1]}"
  elif [[ "$cwd" =~ \.pipeline-worktrees/(adhoc-[0-9]+) ]]; then
    echo "${BASH_REMATCH[1]}"
  fi
}

# Write per-agent metadata sidecar file
write_agent_meta() {
  local agent="$1"
  local model="$2"
  local started_epoch="$3"
  local finished_epoch="$4"
  local exit_code="$5"
  local log_file="$6"
  local tokens_json="${7:-{}}"

  local meta_file="$LOG_DIR/${TIMESTAMP}_${agent}.meta.json"
  local log_bytes=0
  local log_lines=0

  if [[ -f "$log_file" ]]; then
    log_bytes=$(wc -c < "$log_file" | tr -d ' ')
    log_lines=$(wc -l < "$log_file" | tr -d ' ')
  fi

  local duration=$(( finished_epoch - started_epoch ))

  # Task and worker context for Activity Log
  local task_slug; task_slug=$(_task_slug "$TASK_MESSAGE" 2>/dev/null || echo "")
  local worker_id; worker_id=$(_detect_worker_id)

  cat > "$meta_file" << META_EOF
{
  "agent": "$agent",
  "model": "$model",
  "task": "$task_slug",
  "worker": "${worker_id:-main}",
  "started_epoch": $started_epoch,
  "finished_epoch": $finished_epoch,
  "duration_seconds": $duration,
  "exit_code": $exit_code,
  "log_file": "$(basename "$log_file")",
  "log_bytes": $log_bytes,
  "log_lines": $log_lines,
  "tokens": $tokens_json
}
META_EOF
}

# ── Pipeline cost tracking ──────────────────────────────────────────

CUMULATIVE_COST=0

check_cost_budget() {
  if [[ -z "$PIPELINE_MAX_COST" ]]; then
    return 0  # no budget set
  fi

  local over
  over=$(echo "$CUMULATIVE_COST $PIPELINE_MAX_COST" | awk '{if ($1 > $2) print "yes"; else print "no"}')

  if [[ "$over" == "yes" ]]; then
    echo -e "${RED}✗ Pipeline cost budget exceeded: \$${CUMULATIVE_COST} > \$${PIPELINE_MAX_COST}${NC}"
    send_telegram "🛑 <b>Pipeline BUDGET EXCEEDED</b>
💰 Spent: \$${CUMULATIVE_COST} / \$${PIPELINE_MAX_COST}
📋 <i>${TASK_MESSAGE}</i>"
    return 1
  fi
  return 0
}

# ── Profile system ──────────────────────────────────────────────────

PROFILES_FILE="$PIPELINE_DIR/profiles.json"

apply_profile() {
  local profile_name="$1"

  if [[ ! -f "$PROFILES_FILE" ]]; then
    echo -e "${YELLOW}⚠ Profiles file not found: ${PROFILES_FILE}${NC}"
    return 1
  fi

  if ! jq -e ".\"${profile_name}\"" "$PROFILES_FILE" &>/dev/null; then
    echo -e "${YELLOW}⚠ Unknown profile: ${profile_name}${NC}"
    return 1
  fi

  echo -e "${CYAN}Applying profile: ${profile_name}${NC}"

  # Override AGENTS array
  local agents_json
  agents_json=$(jq -r ".\"${profile_name}\".agents[]" "$PROFILES_FILE" 2>/dev/null)
  if [[ -n "$agents_json" ]]; then
    AGENTS=()
    while IFS= read -r agent; do
      AGENTS+=("$agent")
    done <<< "$agents_json"
    echo -e "  ${BLUE}Agents: ${AGENTS[*]}${NC}"
  fi

  # Override timeouts
  local timeouts
  timeouts=$(jq -r ".\"${profile_name}\".timeout_overrides // {} | to_entries[] | \"\(.key)=\(.value)\"" "$PROFILES_FILE" 2>/dev/null)
  while IFS='=' read -r key val; do
    [[ -z "$key" ]] && continue
    local var="PIPELINE_TIMEOUT_$(echo "$key" | tr '[:lower:]' '[:upper:]')"
    eval "$var=$val"
    echo -e "  ${BLUE}Timeout ${key}: ${val}s${NC}"
  done <<< "$timeouts"
}

# ── Planner agent integration ──────────────────────────────────────

PLAN_FILE="$REPO_ROOT/pipeline-plan.json"
PIPELINE_TIMEOUT_PLANNER="${PIPELINE_TIMEOUT_PLANNER:-300}"  # 5 min
FALLBACK_PLANNER="${PIPELINE_FALLBACK_PLANNER:-openrouter/google/gemini-2.0-flash-exp,free,cheap}"

apply_plan() {
  local plan_file="$1"

  if [[ ! -f "$plan_file" ]]; then
    echo -e "${YELLOW}⚠ Plan file not found, using standard profile${NC}"
    return 1
  fi

  if ! jq -e '.' "$plan_file" &>/dev/null; then
    echo -e "${YELLOW}⚠ Invalid plan JSON, using standard profile${NC}"
    return 1
  fi

  local profile
  profile=$(jq -r '.profile // "standard"' "$plan_file")
  local reasoning
  reasoning=$(jq -r '.reasoning // ""' "$plan_file")

  echo -e "${CYAN}Planner chose profile: ${profile}${NC}"
  emit_event "PLAN" "profile=${profile}|agents=$(jq -r '.agents // [] | join(" → ")' "$plan_file" 2>/dev/null)"
  if [[ -n "$reasoning" ]]; then
    echo -e "  ${BLUE}Reasoning: ${reasoning}${NC}"
  fi

  # Apply profile from profiles.json
  PIPELINE_PROFILE="$profile"
  apply_profile "$profile" || true

  # Override agents if planner specified a custom list
  local custom_agents
  custom_agents=$(jq -r '.agents // [] | .[]' "$plan_file" 2>/dev/null)
  if [[ -n "$custom_agents" ]]; then
    AGENTS=()
    while IFS= read -r agent; do
      AGENTS+=("$agent")
    done <<< "$custom_agents"
    echo -e "  ${BLUE}Custom agents: ${AGENTS[*]}${NC}"
  fi

  # Apply timeout overrides from plan
  local timeouts
  timeouts=$(jq -r '.timeout_overrides // {} | to_entries[] | "\(.key)=\(.value)"' "$plan_file" 2>/dev/null)
  while IFS='=' read -r key val; do
    [[ -z "$key" ]] && continue
    local var="PIPELINE_TIMEOUT_$(echo "$key" | tr '[:lower:]' '[:upper:]')"
    eval "$var=$val"
  done <<< "$timeouts"

  # Apply model overrides from plan
  local model_overrides
  model_overrides=$(jq -r '.model_overrides // {} | to_entries[] | "\(.key)=\(.value)"' "$plan_file" 2>/dev/null)
  while IFS='=' read -r key val; do
    [[ -z "$key" ]] && continue
    swap_agent_model "$key" "$val" 2>/dev/null || true
  done <<< "$model_overrides"

  # Auto-inject auditor for agent-related tasks
  if [[ "$ENABLE_AUDIT" != true ]]; then
    auto_inject_auditor "$plan_file"
  fi
}

# ── Auto-audit detection for agent tasks ─────────────────────────────

auto_inject_auditor() {
  local plan_file="$1"

  # Trust planner's decision — it uses Opus and analyzes the task in detail
  local is_agent_task="false"
  if [[ -f "$plan_file" ]]; then
    is_agent_task=$(jq -r '.is_agent_task // false' "$plan_file" 2>/dev/null || echo "false")
  fi

  # Fallback only when plan.json is missing: check apps_affected for -agent dirs
  if [[ "$is_agent_task" != "true" && ! -f "$plan_file" ]]; then
    local task_lower
    task_lower=$(echo "$TASK_MESSAGE" | tr '[:upper:]' '[:lower:]')
    if echo "$task_lower" | grep -qE '(new agent|create agent|add .+-agent)'; then
      is_agent_task="true"
    fi
  fi

  if [[ "$is_agent_task" == "true" ]]; then
    # Check if auditor is already in the list
    local has_auditor=false
    for a in "${AGENTS[@]}"; do
      [[ "$a" == "auditor" ]] && has_auditor=true
    done

    if [[ "$has_auditor" == false ]]; then
      # Insert auditor right after coder
      local new_agents=()
      for a in "${AGENTS[@]}"; do
        new_agents+=("$a")
        if [[ "$a" == "coder" ]]; then
          new_agents+=("auditor")
        fi
      done
      AGENTS=("${new_agents[@]}")
      ENABLE_AUDIT=true
      echo -e "${CYAN}🔍 Agent task detected — auditor auto-injected after coder${NC}"
      echo -e "  ${BLUE}Agents: ${AGENTS[*]}${NC}"
    fi
  fi
}

# ── Anti-loop monitor ────────────────────────────────────────────────

monitor_agent_loop() {
  local log_file="$1"
  local agent="$2"
  local agent_pid="$3"
  local check_interval=60
  local max_stalls=3
  local prev_size=0
  local stall_count=0

  local monitor_start
  monitor_start=$(date +%s)

  while kill -0 "$agent_pid" 2>/dev/null; do
    sleep "$check_interval"
    [[ -f "$log_file" ]] || continue

    local cur_size
    cur_size=$(wc -c < "$log_file" 2>/dev/null | tr -d ' ' || echo 0)

    # Check 1: Log file not growing (agent stalled)
    if [[ "$cur_size" -eq "$prev_size" && "$cur_size" -gt 0 ]]; then
      stall_count=$((stall_count + 1))
      if [[ $stall_count -ge $max_stalls ]]; then
        echo "LOOP_DETECTED:stall:Log not growing for $((max_stalls * check_interval))s" > "${log_file}.loop"
        kill "$agent_pid" 2>/dev/null
        return
      fi
    else
      stall_count=0
    fi

    # Check 2: Repeated error patterns in recent output
    if [[ "$cur_size" -gt 1000 ]]; then
      local recent_errors
      recent_errors=$(tail -100 "$log_file" 2>/dev/null | grep -ciE 'error|failed|exception' 2>/dev/null || echo 0)
      if [[ "$recent_errors" -gt 30 ]]; then
        local unique_errors
        unique_errors=$(tail -100 "$log_file" 2>/dev/null | grep -iE 'error|failed|exception' | sort -u | wc -l | tr -d ' ')
        if [[ "$unique_errors" -lt 3 ]]; then
          echo "LOOP_DETECTED:repeated_errors:${recent_errors} errors with only ${unique_errors} unique patterns" > "${log_file}.loop"
          kill "$agent_pid" 2>/dev/null
          return
        fi
      fi
    fi

    # Check 3: Iteration counting for validator/tester
    # Detects repeated make cycles (cs-fix/analyse/test) that aren't making progress
    if [[ "$agent" == "validator" || "$agent" == "tester" ]]; then
      local make_runs
      make_runs=$(grep -cE 'make (cs-fix|cs-check|analyse|test|knowledge-|hello-|news-)' "$log_file" 2>/dev/null || echo 0)
      local max_iterations=8  # ~4 cycles of fix+check
      if [[ "$make_runs" -gt "$max_iterations" ]]; then
        # Check if errors are decreasing — if not, it's a loop
        local recent_errors prev_errors
        recent_errors=$(tail -50 "$log_file" 2>/dev/null | grep -ciE 'error|ERROR' 2>/dev/null || echo 0)
        prev_errors=$(sed -n '1,50p' "$log_file" 2>/dev/null | grep -ciE 'error|ERROR' 2>/dev/null || echo 0)
        if [[ "$recent_errors" -ge "$prev_errors" && "$recent_errors" -gt 0 ]]; then
          echo "LOOP_DETECTED:iteration_limit:${make_runs} make runs, errors not decreasing (${prev_errors}->${recent_errors})" > "${log_file}.loop"
          kill "$agent_pid" 2>/dev/null
          return
        fi
      fi
    fi

    prev_size=$cur_size
  done
}

# ── Verify coder produced real code changes ──────────────────────────
#
# After the coder stage, check that actual source files were modified
# (not just handoff.md or pipeline metadata). If coder produced nothing,
# it likely hit a permission error (e.g. worktree external_directory rejection)
# and downstream stages would run on unchanged code — a silent no-op.

verify_coder_output() {
  echo -e "  ${BLUE}Verifying coder produced code changes...${NC}"

  # Get list of changed files (staged + unstaged + untracked), excluding pipeline metadata
  local changed_files
  changed_files=$(git -C "$REPO_ROOT" diff --name-only HEAD~1 2>/dev/null || echo "")

  # Also check uncommitted changes
  local uncommitted
  uncommitted=$(git -C "$REPO_ROOT" diff --name-only 2>/dev/null || echo "")
  local untracked
  untracked=$(git -C "$REPO_ROOT" ls-files --others --exclude-standard 2>/dev/null || echo "")

  local all_changes
  all_changes=$(printf '%s\n%s\n%s' "$changed_files" "$uncommitted" "$untracked" | sort -u)

  # Filter out pipeline metadata — only count real source files
  local real_changes
  real_changes=$(echo "$all_changes" | grep -vE '^\.opencode/|^\.pipeline-task|^handoff\.md$|^$' || true)

  if [[ -z "$real_changes" ]]; then
    echo -e "  ${RED}✗ Coder produced NO source file changes${NC}"
    echo -e "  ${YELLOW}  Only pipeline metadata was modified. The coder agent likely failed silently.${NC}"
    return 1
  fi

  local file_count
  file_count=$(echo "$real_changes" | wc -l | tr -d ' ')
  echo -e "  ${GREEN}✓ Coder modified ${file_count} source file(s)${NC}"
  return 0
}

# ── Run a single agent with timeout and retry ─────────────────────────

run_agent() {
  local agent="$1"
  local message="$2"
  local log_file="$LOG_DIR/${TIMESTAMP}_${agent}.log"
  local agent_timeout
  agent_timeout=$(get_timeout "$agent")
  local timeout_min=$(( agent_timeout / 60 ))

  # Save original model for restoration
  local original_model
  original_model=$(get_current_model "$agent")

  # Build list of models to try: primary + fallbacks
  local fallback_chain
  fallback_chain=$(get_fallback_chain "$agent")
  local models_to_try="$original_model"
  if [[ -n "$fallback_chain" ]]; then
    models_to_try="${original_model},${fallback_chain}"
  fi

  emit_event "AGENT_START" "agent=${agent}|model=${original_model}"

  echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  echo -e "${BLUE}▶ Agent:   ${YELLOW}${agent}${NC}"
  echo -e "${BLUE}▶ Model:   ${NC}${original_model}"
  echo -e "${BLUE}▶ Fallback:${NC} ${fallback_chain:-none}"
  echo -e "${BLUE}▶ Started: ${NC}$(date '+%H:%M:%S')"
  echo -e "${BLUE}▶ Timeout: ${NC}${timeout_min} min"
  echo -e "${BLUE}▶ Log:     ${NC}${log_file}"
  echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  echo ""

  local attempt=0
  local max_attempts=$(( MAX_RETRIES + 1 ))
  local fallback_index=0

  # Split fallback chain into array
  IFS=',' read -r -a fallback_models <<< "$fallback_chain"

  while [[ $attempt -lt $max_attempts ]]; do
    attempt=$((attempt + 1))

    if [[ $attempt -gt 1 ]]; then
      echo -e "${YELLOW}  Retry ${attempt}/${max_attempts} after ${RETRY_DELAY}s...${NC}"
      sleep "$RETRY_DELAY"
    fi

    # Run with timeout, tracking PID for loop monitor
    # NOTE: We cd into REPO_ROOT instead of using --dir because opencode
    # registers git worktrees as "sandboxes" with restricted permissions
    # when passed via --dir. Running from CWD lets opencode detect the
    # worktree as an independent project with full file access.
    local exit_code=0
    local agent_start_epoch
    agent_start_epoch=$(date +%s)

    if command -v timeout &>/dev/null; then
      (cd "$REPO_ROOT" && timeout "$agent_timeout" opencode run \
        --agent "$agent" \
        "$message") \
        < /dev/null 2>&1 | tee "$log_file" &
    else
      (cd "$REPO_ROOT" && opencode run \
        --agent "$agent" \
        "$message") \
        < /dev/null 2>&1 | tee "$log_file" &
    fi
    local agent_pid=$!

    # Start loop monitor in background
    monitor_agent_loop "$log_file" "$agent" "$agent_pid" &
    local monitor_pid=$!

    # Wait for agent to finish
    wait "$agent_pid" 2>/dev/null || exit_code=$?

    # Kill monitor
    kill "$monitor_pid" 2>/dev/null
    wait "$monitor_pid" 2>/dev/null

    local agent_end_epoch
    agent_end_epoch=$(date +%s)

    # Capture session ID for token tracking
    local session_id
    session_id=$(get_latest_session_id)

    # Check if loop was detected
    if [[ -f "${log_file}.loop" ]]; then
      local loop_info
      loop_info=$(cat "${log_file}.loop")
      rm -f "${log_file}.loop"
      echo -e "${RED}✗ Loop detected for '${agent}': ${loop_info}${NC}"

      # Query tokens from opencode session export
      local tokens_json
      tokens_json=$(query_session_tokens "$session_id")
      write_agent_meta "$agent" "$(get_current_model "$agent")" "$agent_start_epoch" "$agent_end_epoch" "2" "$log_file" "$tokens_json"

      # Store token results for report
      set_agent_tokens "$agent" "$tokens_json"

      local agent_cost
      agent_cost=$(echo "$tokens_json" | jq -r '.cost' 2>/dev/null || echo 0)
      CUMULATIVE_COST=$(echo "$CUMULATIVE_COST $agent_cost" | awk '{printf "%.4f", $1 + $2}')

      send_telegram "🔄 <b>${agent}</b> LOOP DETECTED
📋 <i>${loop_info}</i>
💰 Cost so far: \$${CUMULATIVE_COST}"

      restore_agent_model "$agent" "$original_model"
      return 1
    fi

    # Query token usage from opencode session export
    local tokens_json
    tokens_json=$(query_session_tokens "$session_id")
    write_agent_meta "$agent" "$(get_current_model "$agent")" "$agent_start_epoch" "$agent_end_epoch" "$exit_code" "$log_file" "$tokens_json"

    # Store token results for report
    set_agent_tokens "$agent" "$tokens_json"

    local agent_cost
    agent_cost=$(echo "$tokens_json" | jq -r '.cost' 2>/dev/null || echo 0)
    CUMULATIVE_COST=$(echo "$CUMULATIVE_COST $agent_cost" | awk '{printf "%.4f", $1 + $2}')

    # Check result
    if [[ $exit_code -eq 0 ]]; then
      local in_tok out_tok cache_r cache_w
      in_tok=$(echo "$tokens_json" | jq -r '.input_tokens' 2>/dev/null || echo 0)
      out_tok=$(echo "$tokens_json" | jq -r '.output_tokens' 2>/dev/null || echo 0)
      cache_r=$(echo "$tokens_json" | jq -r '.cache_read' 2>/dev/null || echo 0)
      cache_w=$(echo "$tokens_json" | jq -r '.cache_write' 2>/dev/null || echo 0)

      local agent_dur=$(( agent_end_epoch - agent_start_epoch ))
      emit_event "AGENT_DONE" "agent=${agent}|status=ok|duration=${agent_dur}s|tokens=${in_tok}/${out_tok}|cache=${cache_r}"

      echo ""
      echo -e "${GREEN}✓ Agent '${agent}' completed successfully${NC}"
      echo -e "  ${BLUE}Tokens: ${in_tok} in / ${out_tok} out | Cache: ${cache_r} read / ${cache_w} write${NC}"
      restore_agent_model "$agent" "$original_model"

      # Check cost budget
      if ! check_cost_budget; then
        return 1
      fi
      return 0
    elif [[ $exit_code -eq 124 ]]; then
      emit_event "AGENT_DONE" "agent=${agent}|status=timeout|duration=${timeout_min}m"
      echo -e "${RED}✗ Agent '${agent}' timed out after ${timeout_min} min${NC}"
      restore_agent_model "$agent" "$original_model"
      return 1
    else
      emit_event "AGENT_DONE" "agent=${agent}|status=fail|exit=${exit_code}"
      echo -e "${RED}✗ Agent '${agent}' failed (exit code: ${exit_code})${NC}"

      # Check if it's a rate limit error — try fallback model
      if is_rate_limit_error "$log_file" && [[ $fallback_index -lt ${#fallback_models[@]} ]]; then
        local next_model="${fallback_models[$fallback_index]}"
        fallback_index=$((fallback_index + 1))
        echo -e "${YELLOW}  Rate limit detected — switching to fallback model${NC}"
        swap_agent_model "$agent" "$next_model"
        # Don't count fallback as a retry — reset attempt counter
        attempt=$((attempt - 1))
        sleep 5
      elif [[ $attempt -lt $max_attempts ]]; then
        echo -e "${YELLOW}  Will retry...${NC}"
      fi
    fi
  done

  # Restore original model before returning
  restore_agent_model "$agent" "$original_model"

  echo -e "${RED}✗ Agent '${agent}' failed after ${max_attempts} attempts${NC}"
  echo -e "${YELLOW}  Check log: ${log_file}${NC}"
  return 1
}

# ── Build the prompt for each agent ───────────────────────────────────

build_prompt() {
  local agent="$1"

  case "$agent" in
    planner)
      cat << PROMPT
Task: ${TASK_MESSAGE}

## Your Role

Analyze the task and output a JSON pipeline configuration. Do NOT write any code or specs.

## Analysis Steps

1. Read the task description carefully
2. Search codebase for files/patterns mentioned in the task (use glob/grep)
3. Check if existing OpenSpec proposals cover this: \`npx openspec list\`
4. Estimate scope: how many files, apps, services are likely affected?
5. Check if DB migrations are likely needed (schema changes, new tables)
6. Check if API surface changes are needed (new/modified endpoints)

## Profile Guide

- **quick-fix**: Typos, config tweaks, minor fixes, single-file edits, slide updates. 1-3 files affected.
- **standard**: Normal features, moderate changes. Multiple files, single app. May need OpenSpec.
- **complex**: Multi-service changes, DB migrations, API changes, new agents. Cross-app impact.

## Output

Write ONLY a JSON file to \`pipeline-plan.json\` (in the repo root) with this exact structure:

\`\`\`json
{
  "profile": "quick-fix|standard|complex",
  "reasoning": "Brief explanation of complexity assessment",
  "agents": ["list", "of", "agents", "to", "run"],
  "skip_openspec": true,
  "estimated_files": 5,
  "apps_affected": ["core"],
  "needs_migration": false,
  "needs_api_change": false,
  "is_agent_task": false,
  "timeout_overrides": {},
  "model_overrides": {}
}
\`\`\`

**Fields**:
- \`is_agent_task\`: set to \`true\` when the task creates, modifies, or significantly changes an agent (any app in \`apps/\` with \`-agent\` suffix, or agent configs in \`.opencode/agents/\`). When true, the pipeline auto-injects an auditor step after the coder to verify agent compliance.

Agent options: planner, architect, coder, auditor, validator, tester, documenter, summarizer.
For quick-fix: typically ["coder", "validator", "summarizer"].
For standard: typically ["architect", "coder", "validator", "tester", "summarizer"].
For complex: add "auditor" and increase timeouts.
Note: documenter is NOT needed by default — coder handles docs via tasks.md "Documentation" section. Only add documenter if documentation is the primary task.
Always keep "summarizer" as the final agent unless this is an explicit single-agent run.

**IMPORTANT**: If an existing OpenSpec proposal already has \`tasks.md\` — exclude \`architect\` from agents. The coder reads specs directly from \`openspec/changes/<id>/\`. If the task says "Implement openspec change ..." — the spec is ready, skip architect.

Write the file and nothing else. Do not explain your reasoning outside the JSON.
PROMPT
      ;;
    architect)
      local architect_timeout
      architect_timeout=$(get_timeout "architect")
      local architect_timeout_min=$(( architect_timeout / 60 ))

      local scope_instruction=""
      case "$PIPELINE_PROFILE" in
        quick-fix)
          scope_instruction="
## Scope Note
This is a quick-fix task. Create ONLY proposal.md with a one-paragraph description.
Skip design.md, tasks.md, and spec deltas entirely.
Do NOT explore the codebase beyond the files mentioned in the task.
Target: complete in under 5 minutes.
"
          ;;
        standard)
          scope_instruction="
## Scope Note
Create a MINIMAL proposal: proposal.md and tasks.md only.
Skip design.md and detailed spec deltas unless the task involves new API endpoints or DB schema changes.
Limit codebase exploration to files directly mentioned in the task — do NOT read more than 10 files.
Focus on getting to the coder stage quickly.
Target: complete in under 20 minutes.
"
          ;;
        complex)
          scope_instruction="
## Scope Note
Create full proposal with design.md. Limit codebase exploration to 20 files max.
Focus spec deltas on changed components only, not the entire spec tree.
"
          ;;
      esac

      cat << PROMPT
Task: ${TASK_MESSAGE}
${scope_instruction}
Time budget: ~${architect_timeout_min} minutes. Plan your work accordingly.

## Instructions

1. Read \`openspec/AGENTS.md\` for full OpenSpec conventions
2. Read \`openspec/project.md\` to understand current project state
3. Run \`openspec list\` — if a proposal for this task already exists, update it instead of creating a duplicate
4. Explore the codebase with grep/glob/read to understand current implementation
5. Search existing requirements: \`rg -n "Requirement:|Scenario:" openspec/specs\`
6. Scaffold under \`openspec/changes/<id>/\`: proposal.md, design.md, tasks.md, and spec deltas
7. Validate: \`openspec validate <id> --strict\` — fix all issues before finishing

## Handoff

Update \`.opencode/pipeline/handoff.md\` with your section:
- Change ID created
- Apps affected (core, knowledge-agent, hello-agent, news-maker-agent)
- Whether DB changes (migrations) are needed
- API surface changes (new/modified endpoints)
- Key design decisions and risks
PROMPT
      ;;
    coder)
      cat << PROMPT
Task: ${TASK_MESSAGE}

## Instructions

1. Read \`.opencode/pipeline/handoff.md\` for context from the architect (if it exists)
2. Read the full proposal: \`openspec/changes/<id>/proposal.md\`, \`design.md\`, \`tasks.md\`
3. Read spec deltas in \`openspec/changes/<id>/specs/\`
4. Implement tasks from \`tasks.md\` sequentially, marking each \`- [x]\` when done
5. Follow existing codebase patterns — read surrounding code before writing

## Per-App Make Targets

| App | Test | Analyse | CS Check | Migrate |
|-----|------|---------|----------|---------|
| apps/core/ | make test | make analyse | make cs-check | make migrate |
| apps/knowledge-agent/ | make knowledge-test | make knowledge-analyse | make knowledge-cs-check | make knowledge-migrate |
| apps/hello-agent/ | make hello-test | make hello-analyse | make hello-cs-check | — |
| apps/news-maker-agent/ | make news-test | make news-analyse | make news-cs-check | make news-migrate |

## Important

- After creating migration files, run \`make migrate\` (or the per-app variant)
- If migration fails, fix it before proceeding
- Keep edits minimal and focused on the spec
- **NEVER delete or modify files that are NOT mentioned in tasks.md** — only touch files required by the spec

## Handoff

Update \`.opencode/pipeline/handoff.md\` — Coder section:
- List every file created or modified
- List any migration files created
- Note deviations from the spec (with reasoning)
PROMPT
      ;;
    validator)
      cat << PROMPT
Read \`.opencode/pipeline/handoff.md\` for the task description and full pipeline context.

## Instructions

1. From handoff.md, determine which apps were changed
2. Run validation ONLY for changed apps (not the entire codebase):

| App | CS Check | Analyse |
|-----|----------|---------|
| apps/core/ | make cs-check | make analyse |
| apps/knowledge-agent/ | make knowledge-cs-check | make knowledge-analyse |
| apps/hello-agent/ | make hello-cs-check | make hello-analyse |
| apps/news-maker-agent/ | make news-cs-check | make news-analyse |

3. For CS issues: run the corresponding \`cs-fix\` target first, then verify
4. For PHPStan errors: read the failing file, understand the error, fix manually
5. If \`phpstan-baseline.neon\` exists, preserve existing suppressions — only fix NEW errors
6. Re-run all checks after fixes. Iterate until zero errors.

## Handoff

Update \`.opencode/pipeline/handoff.md\` — Validator section:
- PHPStan result: pass/fail (per app)
- CS-check result: pass/fail (per app)
- Files fixed (list)
PROMPT
      ;;
    tester)
      cat << PROMPT
Read \`.opencode/pipeline/handoff.md\` for the task description and full pipeline context.

## Instructions

1. From handoff.md, determine which apps and files were changed
2. Run the relevant test suite(s) — ONLY for changed apps:

| App | Unit+Functional | Convention |
|-----|-----------------|------------|
| apps/core/ | make test | make conventions-test |
| apps/knowledge-agent/ | make knowledge-test | make conventions-test |
| apps/hello-agent/ | make hello-test | make conventions-test |
| apps/news-maker-agent/ | make news-test | make conventions-test |

3. If tests fail: read the failing test AND the tested code, determine root cause, fix it
4. Check test coverage for new code — if new classes/methods have no tests, write them
5. Follow existing test patterns: Codeception Cest format for PHP, pytest for Python
6. If the change touches agent config (manifest, compose), also run: \`make conventions-test\`
7. Run the full suite one last time to ensure nothing is broken

## Test Conventions

- PHP test files: \`tests/Unit/\` and \`tests/Functional/\`, mirroring \`src/\` structure
- Test naming: \`*Cest.php\` (Codeception), test methods with \`test\` prefix
- Reference: \`docs/agent-requirements/test-cases.md\` (TC-01..TC-05)
- Reference: \`docs/agent-requirements/e2e-testing.md\` for isolation patterns

## Handoff

Update \`.opencode/pipeline/handoff.md\` — Tester section:
- Test results per suite (passed/failed/skipped counts)
- New tests written (file paths)
- Tests updated and why
PROMPT
      ;;
    documenter)
      cat << PROMPT
Read \`.opencode/pipeline/handoff.md\` for the task description and full pipeline context.

## Instructions

1. From handoff.md, understand what was implemented and which apps were changed
2. Read the OpenSpec proposal (\`proposal.md\`, \`design.md\`) for what was implemented
3. Read \`.claude/skills/documentation/SKILL.md\` for documentation conventions
4. Read \`INDEX.md\` (project root) to understand the current doc landscape
5. Determine what documentation needs to be created or updated:
   - New agent → \`docs/agents/ua/\` and \`docs/agents/en/\`
   - New feature → \`docs/features/ua/\` and \`docs/features/en/\`
   - API changes → \`docs/specs/\`
   - Config changes → \`docs/local-dev.md\` or agent-specific docs
6. Write/update documentation using templates from the documentation skill
7. Update \`INDEX.md\` with new entries

## Validation

After writing docs, verify:
- No .md files in intermediate directories (dirs with subdirs must NOT contain .md files)
- \`INDEX.md\` updated with all new entries
- Both \`ua/\` and \`en/\` versions exist for bilingual sections with identical structure

## Handoff

Update \`.opencode/pipeline/handoff.md\` — Documenter section:
- Docs created/updated (file paths)
- Final status: PIPELINE COMPLETE
PROMPT
      ;;
    auditor)
      local task_summary
      task_summary=$(echo "$TASK_MESSAGE" | head -1 | cut -c1-120)
      cat << PROMPT
Task: Audit the changes from pipeline — ${task_summary}

## Instructions

1. Read \`.opencode/pipeline/handoff.md\` to understand what was changed
2. Read \`.claude/skills/agent-auditor/SKILL.md\` for the audit checklist
3. Determine which agents/apps were modified
4. Run the appropriate checklist (PHP or Python) against the changed agents
5. Run the platform checklist for cross-cutting concerns
6. Generate an audit report following \`.claude/skills/agent-auditor/references/report-template.md\`

## Focus Areas

- Structure & Build (S): Dockerfile, composer.json, service config
- Testing (T): test coverage, PHPStan, CS compliance
- Configuration (C): manifest endpoint, Agent Card fields
- Security (X): no hardcoded secrets, proper auth
- Observability (O): trace context, Langfuse integration, structured logging
- Documentation (D): bilingual docs exist, INDEX.md updated

## Output

Write audit report to \`.opencode/pipeline/reports/${TIMESTAMP}_audit.md\`
Update \`.opencode/pipeline/handoff.md\` with audit summary and verdict (PASS/WARN/FAIL)
PROMPT
      ;;
    summarizer)
      cat << PROMPT
Task: Create the final task summary for this pipeline run.

## Instructions

1. Read \`.opencode/pipeline/handoff.md\` for cross-agent context.
2. Read \`${CHECKPOINT_FILE}\` to see which agents actually ran, their statuses, durations, and commits.
3. Read the available logs in \`.opencode/pipeline/logs/${TIMESTAMP}_*.log\`.
4. Read \`.opencode/pipeline/reports/${TIMESTAMP}.md\` if it already exists.
5. Write the final markdown summary to \`${TASK_SUMMARY_FILE}\`.
6. Write the report in Ukrainian.

## Required Report Structure

\`\`\`md
# Task Summary: <title>

## Загальний статус
- Статус пайплайну
- Гілка
- Pipeline ID

## Агенти
### <agent>
- Що зробив
- Які були складнощі або блокери
- Що залишилось виправити або доробити

## Що треба доробити
- ...

## Пропозиція до наступної задачі
- Назва задачі
- Чому її варто створити зараз
- Очікуваний результат
\`\`\`

## Rules

- Include only agents that actually worked on the task.
- If an agent log shows no blocking issues, say so explicitly.
- If the pipeline failed, clearly name the failing agent and the unfinished work.
- End with exactly one concrete proposed follow-up task.
- Do not overwrite other reports; write only to \`${TASK_SUMMARY_FILE}\`.

## Handoff

Update \`.opencode/pipeline/handoff.md\` — Summarizer section:
- Status
- Summary file path
- Final recommendation for next task
PROMPT
      ;;
  esac
}

# ── Initialize handoff file ──────────────────────────────────────────

init_handoff() {
  mkdir -p "$PIPELINE_DIR"

  # Only create new handoff if not resuming (--from)
  if [[ -n "$FROM_AGENT" && -f "$HANDOFF_FILE" ]]; then
    echo -e "${BLUE}Using existing handoff file (resuming from ${FROM_AGENT})${NC}"
    return
  fi

  cat > "$HANDOFF_FILE" << EOF
# Pipeline Handoff

- **Task**: ${TASK_MESSAGE}
- **Started**: $(date '+%Y-%m-%d %H:%M:%S')
- **Branch**: ${branch}
- **Pipeline ID**: ${TIMESTAMP}

---

## Architect

- **Status**: pending
- **Change ID**: —
- **Apps affected**: —
- **DB changes**: —
- **API changes**: —

## Coder

- **Status**: pending
- **Files modified**: —
- **Migrations created**: —
- **Deviations**: —

## Validator

- **Status**: pending
- **PHPStan**: —
- **CS-check**: —
- **Files fixed**: —

## Tester

- **Status**: pending
- **Test results**: —
- **New tests written**: —

## Auditor

- **Status**: pending
- **Verdict**: —
- **Recommendations**: —

## Documenter

- **Status**: pending
- **Docs created/updated**: —

## Summarizer

- **Status**: pending
- **Summary file**: —
- **Next task recommendation**: —

---

EOF
}

# ── Main execution ───────────────────────────────────────────────────

main() {
  echo ""
  echo -e "${CYAN}╔══════════════════════════════════════════════════╗${NC}"
  echo -e "${CYAN}║${NC}     ${YELLOW}OpenCode Multi-Agent Pipeline v2${NC}             ${CYAN}║${NC}"
  echo -e "${CYAN}╚══════════════════════════════════════════════════╝${NC}"
  echo ""
  echo -e "${BLUE}Task:${NC} ${TASK_MESSAGE}"
  echo -e "${BLUE}Time:${NC} $(date '+%Y-%m-%d %H:%M:%S')"
  echo ""

  # Pre-flight
  preflight

  # Setup branch
  branch=$(setup_branch)
  echo -e "${BLUE}Branch:${NC} ${branch}"

  # Create or switch to branch (with retry for lock contention in parallel mode)
  local branch_ok=false
  for _try in 1 2 3 4 5; do
    if git -C "$REPO_ROOT" rev-parse --verify "$branch" &>/dev/null; then
      # Branch exists — re-create from current HEAD in worktree mode
      if [[ -f "$REPO_ROOT/.git" ]]; then
        git -C "$REPO_ROOT" branch -D "$branch" 2>/dev/null || true
        if git -C "$REPO_ROOT" checkout -b "$branch" 2>/dev/null; then
          echo -e "${YELLOW}Re-created branch (worktree re-run): ${branch}${NC}"
          branch_ok=true; break
        fi
      else
        if git -C "$REPO_ROOT" checkout "$branch" 2>/dev/null; then
          echo -e "${YELLOW}Switched to existing branch: ${branch}${NC}"
          branch_ok=true; break
        fi
      fi
    else
      if git -C "$REPO_ROOT" checkout -b "$branch" 2>/dev/null; then
        echo -e "${GREEN}Created branch: ${branch}${NC}"
        branch_ok=true; break
      fi
    fi
    echo -e "${YELLOW}Git lock contention (attempt ${_try}/5), retrying in ${_try}s...${NC}"
    sleep "$_try"
  done
  if [[ "$branch_ok" != true ]]; then
    echo -e "${RED}Failed to create/switch branch after 5 attempts${NC}"
    exit 1
  fi

  # Move task to in-progress (if using task file lifecycle)
  _task_move_to_in_progress

  # Initialize handoff
  init_handoff

  # Emit task start event
  local task_title
  task_title=$(echo "$TASK_MESSAGE" | grep -m1 '^# ' | sed 's/^# //' || echo "$TASK_MESSAGE" | head -c 60)
  emit_event "TASK_START" "task=${task_title}"

  # Initialize artifacts & checkpoint
  local slug
  slug=$(_task_slug "$TASK_MESSAGE")
  init_artifacts "$slug" "$branch"

  # Auto-resume: if --resume and checkpoint exists, determine FROM_AGENT
  if [[ "$RESUME_MODE" == true && -z "$FROM_AGENT" ]]; then
    local resume_from
    resume_from=$(get_resume_agent)
    if [[ -n "$resume_from" && "$resume_from" != "architect" ]]; then
      FROM_AGENT="$resume_from"
      echo -e "${YELLOW}Auto-resuming from: ${FROM_AGENT}${NC}"
      echo -e "${DIM}Checkpoint:${NC}"
      print_checkpoint_summary
      echo ""
    fi
  fi

  # Apply profile or run planner
  if [[ -n "$PIPELINE_PROFILE" ]]; then
    apply_profile "$PIPELINE_PROFILE"
  elif [[ "$SKIP_PLANNER" != true && "$SKIP_ARCHITECT" != true && -z "$FROM_AGENT" && -z "$ONLY_AGENT" ]]; then
    echo -e "${CYAN}Running planner agent to analyze task complexity...${NC}"
    local planner_prompt
    planner_prompt=$(build_prompt "planner")
    local planner_start
    planner_start=$(date +%s)
    if run_agent "planner" "$planner_prompt"; then
      local planner_dur=$(( $(date +%s) - planner_start ))
      echo -e "${GREEN}✓ Planner completed in ${planner_dur}s${NC}"
      if apply_plan "$PLAN_FILE"; then
        write_checkpoint "planner" "done" "$planner_dur" ""
      fi
    else
      echo -e "${YELLOW}⚠ Planner failed, using standard pipeline${NC}"
      write_checkpoint "planner" "failed" "$(( $(date +%s) - planner_start ))" ""
    fi
    # Archive plan.json for this task, then clean up git
    if [[ -f "$PLAN_FILE" ]]; then
      local plan_archive="$LOG_DIR/${TIMESTAMP}_plan.json"
      cp "$PLAN_FILE" "$plan_archive" 2>/dev/null || true
    fi
    git -C "$REPO_ROOT" checkout -- "$PLAN_FILE" 2>/dev/null || true
    echo ""
  fi

  # Get agents to run
  local agents_to_run
  agents_to_run=$(get_agents_to_run)
  set_planned_agents $agents_to_run

  echo ""
  echo -e "${BLUE}Agents to run:${NC} $(echo "$agents_to_run" | tr '\n' ' ')"
  echo ""

  # Telegram: pipeline started
  send_telegram "🚀 <b>Pipeline started</b>
📋 <i>${TASK_MESSAGE}</i>
🌿 Branch: <code>${branch}</code>
🤖 Agents: $(echo "$agents_to_run" | tr '\n' ' ')"

  # Task slug for commits
  local task_slug
  task_slug=$(echo "$TASK_MESSAGE" | cut -c1-60)

  local pipeline_start
  pipeline_start=$(date +%s)

  # Run each agent
  local failed=false
  local failed_agent=""
  local should_run_summarizer=false
  if echo "$agents_to_run" | grep -qx 'summarizer'; then
    should_run_summarizer=true
  fi
  for agent in $agents_to_run; do
    local prompt
    prompt=$(build_prompt "$agent")

    local agent_start
    agent_start=$(date +%s)

    # Log file for this agent run
    local agent_log="$LOG_DIR/${TIMESTAMP}_${agent}.log"

    if run_agent "$agent" "$prompt"; then
      local agent_dur=$(( $(date +%s) - agent_start ))

      send_telegram "✅ <b>${agent}</b> completed (${agent_dur}s)
📋 <i>${TASK_MESSAGE}</i>"

      # Auto-commit after each successful agent
      commit_agent_work "$agent" "$task_slug"
      local commit_hash
      commit_hash=$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo "")

      # Save checkpoint & artifacts (with token data)
      local agent_tokens
      agent_tokens=$(get_agent_tokens "$agent")
      write_checkpoint "$agent" "done" "$agent_dur" "$commit_hash" "$agent_tokens"
      save_agent_artifact "$agent" "$agent_log"

      # Stage gate: verify coder produced actual code changes
      if [[ "$agent" == "coder" ]]; then
        if ! verify_coder_output; then
          local agent_dur_fail=$(( $(date +%s) - agent_start ))
          local agent_tokens
      agent_tokens=$(get_agent_tokens "$agent")
          write_checkpoint "$agent" "failed-no-code" "$agent_dur_fail" "" "$agent_tokens"
          failed=true
          failed_agent="$agent (no code produced)"

          send_telegram "❌ <b>${agent}</b> produced NO CODE CHANGES
📋 <i>${TASK_MESSAGE}</i>
⚠️ Coder stage ran but did not modify any source files. Check agent permissions/logs."

          echo -e "${RED}Pipeline stopped: coder produced no code changes${NC}"
          echo -e "${YELLOW}Check log for permission errors (e.g. worktree external_directory rejections)${NC}"
          break
        fi

        run_migrations
      fi

      echo ""
    else
      local agent_dur=$(( $(date +%s) - agent_start ))

      # Save failed checkpoint & artifact (with token data)
      local agent_tokens
      agent_tokens=$(get_agent_tokens "$agent")
      write_checkpoint "$agent" "failed" "$agent_dur" "" "$agent_tokens"
      save_agent_artifact "$agent" "$agent_log"
      failed=true
      failed_agent="$agent"

      send_telegram "❌ <b>${agent}</b> FAILED (${agent_dur}s)
📋 <i>${TASK_MESSAGE}</i>
🔄 Resume: <code>./scripts/pipeline.sh --from ${agent} --branch ${branch} \"...\"</code>"

      echo -e "${RED}Pipeline stopped at agent: ${agent}${NC}"
      echo -e "${YELLOW}Resume with: ./scripts/pipeline.sh --from ${agent} --branch ${branch} \"${TASK_MESSAGE}\"${NC}"
      break
    fi
  done

  if $failed && $should_run_summarizer && [[ "$failed_agent" != "summarizer" ]]; then
    echo ""
    echo -e "${YELLOW}Running summarizer after failure to capture partial task status...${NC}"

    local summary_prompt
    summary_prompt=$(build_prompt "summarizer")
    local summary_start
    summary_start=$(date +%s)
    local summary_log="$LOG_DIR/${TIMESTAMP}_summarizer.log"

    if run_agent "summarizer" "$summary_prompt"; then
      local summary_dur=$(( $(date +%s) - summary_start ))
      commit_agent_work "summarizer" "$task_slug"
      local summary_commit
      summary_commit=$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo "")
      local summary_tokens
      summary_tokens=$(get_agent_tokens "summarizer")
      write_checkpoint "summarizer" "done" "$summary_dur" "$summary_commit" "$summary_tokens"
      save_agent_artifact "summarizer" "$summary_log"
    else
      local summary_dur=$(( $(date +%s) - summary_start ))
      local summary_tokens
      summary_tokens=$(get_agent_tokens "summarizer")
      write_checkpoint "summarizer" "failed" "$summary_dur" "" "$summary_tokens"
      save_agent_artifact "summarizer" "$summary_log"
      echo -e "${YELLOW}⚠ Summarizer failed after pipeline failure; keeping original failure status${NC}"
    fi
  fi

  local total_duration=$(( $(date +%s) - pipeline_start ))

  # Generate report
  local report_file="$REPORT_DIR/${TIMESTAMP}.md"
  {
    echo "# Pipeline Report — ${TIMESTAMP}"
    echo ""
    echo "- **Task**: ${TASK_MESSAGE}"
    echo "- **Branch**: ${branch}"
    if $failed; then
      echo "- **Status**: FAILED at ${failed_agent}"
    else
      echo "- **Status**: COMPLETED"
    fi
    echo "- **Completed**: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "- **Total duration**: ${total_duration}s ($(( total_duration / 60 )) min)"
    echo ""
    echo "## Agent Results"
    echo ""
    echo "| Agent | Status | Duration | Input Tok | Output Tok | Cache Read | Cache Write |"
    echo "|-------|--------|----------|-----------|------------|------------|-------------|"
    # Enhanced report lines with token data from opencode session export
    for agent in $agents_to_run; do
      local agent_tokens
      agent_tokens=$(get_agent_tokens "$agent")
      local in_tok out_tok cache_r cache_w
      in_tok=$(echo "$agent_tokens" | jq -r '.input_tokens // 0' 2>/dev/null || echo 0)
      out_tok=$(echo "$agent_tokens" | jq -r '.output_tokens // 0' 2>/dev/null || echo 0)
      cache_r=$(echo "$agent_tokens" | jq -r '.cache_read // 0' 2>/dev/null || echo 0)
      cache_w=$(echo "$agent_tokens" | jq -r '.cache_write // 0' 2>/dev/null || echo 0)

      # Get status from checkpoint
      local agent_status agent_dur
      agent_status=$(python3 - "$CHECKPOINT_FILE" "$agent" <<'PYEOF' 2>/dev/null || echo "unknown"
import json, sys
with open(sys.argv[1], 'r') as f:
    data = json.load(f)
info = data.get('agents', {}).get(sys.argv[2], {})
print(info.get('status', 'skipped'))
PYEOF
)
      agent_dur=$(python3 - "$CHECKPOINT_FILE" "$agent" <<'PYEOF' 2>/dev/null || echo "0"
import json, sys
with open(sys.argv[1], 'r') as f:
    data = json.load(f)
info = data.get('agents', {}).get(sys.argv[2], {})
print(info.get('duration', 0))
PYEOF
)

      local status_icon="✓"
      if [[ "$agent_status" != "done" ]]; then
        status_icon="✗"
      fi
      echo "| ${agent} | ${status_icon} ${agent_status} | ${agent_dur}s | ${in_tok} | ${out_tok} | ${cache_r} | ${cache_w} |"
    done
    echo ""
    echo "- **Total pipeline cost**: \$${CUMULATIVE_COST}"
    if [[ -n "$PIPELINE_MAX_COST" ]]; then
      echo "- **Cost budget**: \$${PIPELINE_MAX_COST}"
    fi
    if [[ -n "$PIPELINE_PROFILE" ]]; then
      echo "- **Profile**: ${PIPELINE_PROFILE}"
    fi
    echo ""
    echo "## OpenCode Stats"
    echo '```'
    opencode stats 2>/dev/null || echo "(stats unavailable)"
    echo '```'
    echo ""
    echo "## Task Summary"
    echo ""
    echo "- **Summary file**: ${TASK_SUMMARY_FILE}"
  } > "$report_file"

  # Append cost breakdown to task summary file
  if [[ -f "$TASK_SUMMARY_FILE" ]]; then
    {
      echo ""
      echo "---"
      echo ""
      echo "## Вартість пайплайну"
      echo ""
      echo "| Агент | Тривалість | Input | Output | Cache Read | Cache Write | ≈ Вартість |"
      echo "|-------|-----------|-------|--------|------------|-------------|-----------|"
      local grand_in=0 grand_out=0 grand_cr=0 grand_cw=0
      for agent in $agents_to_run; do
        local agent_tokens
        agent_tokens=$(get_agent_tokens "$agent")
        local in_tok out_tok cache_r cache_w agent_dur_s
        in_tok=$(echo "$agent_tokens" | jq -r '.input_tokens // 0' 2>/dev/null || echo 0)
        out_tok=$(echo "$agent_tokens" | jq -r '.output_tokens // 0' 2>/dev/null || echo 0)
        cache_r=$(echo "$agent_tokens" | jq -r '.cache_read // 0' 2>/dev/null || echo 0)
        cache_w=$(echo "$agent_tokens" | jq -r '.cache_write // 0' 2>/dev/null || echo 0)
        agent_dur_s=$(python3 - "$CHECKPOINT_FILE" "$agent" <<'PYEOF' 2>/dev/null || echo "0"
import json, sys
with open(sys.argv[1], 'r') as f:
    data = json.load(f)
print(data.get('agents', {}).get(sys.argv[2], {}).get('duration', 0))
PYEOF
)
        local agent_cost
        agent_cost=$(awk "BEGIN { printf \"%.3f\", ($in_tok * 3 + $out_tok * 15 + $cache_r * 0.30 + $cache_w * 3.75) / 1000000 }")
        grand_in=$((grand_in + in_tok))
        grand_out=$((grand_out + out_tok))
        grand_cr=$((grand_cr + cache_r))
        grand_cw=$((grand_cw + cache_w))
        local dur_fmt="${agent_dur_s}s"
        (( agent_dur_s >= 60 )) && dur_fmt="$(( agent_dur_s / 60 ))m $(( agent_dur_s % 60 ))s"
        echo "| ${agent} | ${dur_fmt} | ${in_tok} | ${out_tok} | ${cache_r} | ${cache_w} | \$${agent_cost} |"
      done
      local grand_cost
      grand_cost=$(awk "BEGIN { printf \"%.3f\", ($grand_in * 3 + $grand_out * 15 + $grand_cr * 0.30 + $grand_cw * 3.75) / 1000000 }")
      echo "| **Всього** | **$(( total_duration / 60 ))m** | **${grand_in}** | **${grand_out}** | **${grand_cr}** | **${grand_cw}** | **\$${grand_cost}** |"
      echo ""
      echo "_Вартість розрахована приблизно за тарифами Claude Sonnet (\$3/\$15 per 1M in/out, \$0.30/\$3.75 cache r/w)._"
    } >> "$TASK_SUMMARY_FILE"
    echo -e "${GREEN}✓ Cost breakdown appended to ${TASK_SUMMARY_FILE}${NC}"
  fi

  # Webhook
  if [[ -n "$WEBHOOK_URL" ]]; then
    local payload="{\"pipeline_id\":\"${TIMESTAMP}\",\"task\":\"${TASK_MESSAGE}\",\"branch\":\"${branch}\",\"status\":\"$(if $failed; then echo failed; else echo completed; fi)\",\"duration_seconds\":${total_duration}}"
    curl -s -X POST "$WEBHOOK_URL" \
      -H "Content-Type: application/json" \
      -d "$payload" &>/dev/null || echo -e "${YELLOW}⚠ Webhook notification failed${NC}"
  fi

  # Send report to dev-reporter-agent (best-effort)
  echo -e "${BLUE}Sending pipeline report to dev-reporter-agent...${NC}"
  if $failed; then
    send_report_to_agent "failed" "$failed_agent" "$total_duration"
  else
    send_report_to_agent "completed" "" "$total_duration"
  fi

  # Telegram: final summary
  if $failed; then
    send_telegram "🔴 <b>Pipeline FAILED</b> at <b>${failed_agent}</b>
📋 <i>${TASK_MESSAGE}</i>
🌿 Branch: <code>${branch}</code>
⏱ Duration: $(( total_duration / 60 ))m"
  else
    send_telegram "🟢 <b>Pipeline COMPLETED</b>
📋 <i>${TASK_MESSAGE}</i>
🌿 Branch: <code>${branch}</code>
⏱ Duration: $(( total_duration / 60 ))m"
  fi

  # Task file lifecycle: move to done or failed
  if $failed; then
    _task_move_to_failed "$branch" "$total_duration"
  else
    _task_move_to_done "$branch" "$total_duration"
  fi

  # Final status
  echo ""
  echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  if $failed; then
    emit_event "TASK_FAIL" "agent=${failed_agent}|duration=$(( total_duration / 60 ))m"
    echo -e "${RED}Pipeline FAILED at agent: ${failed_agent}${NC}"
    echo -e "${BLUE}Report:${NC}  ${report_file}"
    [[ -f "$TASK_SUMMARY_FILE" ]] && echo -e "${BLUE}Task MD:${NC} ${TASK_SUMMARY_FILE}"
    echo -e "${YELLOW}Logs:${NC}    ${LOG_DIR}/${TIMESTAMP}_*.log${NC}"
    exit 1
  else
    emit_event "TASK_DONE" "duration=$(( total_duration / 60 ))m"
    echo -e "${GREEN}Pipeline COMPLETED in $(( total_duration / 60 )) min${NC}"
    echo -e "${BLUE}Branch:${NC}  ${branch}"
    echo -e "${BLUE}Report:${NC}  ${report_file}"
    echo -e "${BLUE}Task MD:${NC} ${TASK_SUMMARY_FILE}"
    echo -e "${BLUE}Handoff:${NC} ${HANDOFF_FILE}"
    echo -e "${BLUE}Logs:${NC}    ${LOG_DIR}/${TIMESTAMP}_*.log${NC}"
  fi
}

main
