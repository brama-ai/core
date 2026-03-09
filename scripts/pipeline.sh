#!/usr/bin/env bash
#
# Multi-agent pipeline orchestrator for OpenCode.
#
# Runs 5 agents in sequence:
#   1. Architect (Opus)     — creates OpenSpec proposal
#   2. Coder (Sonnet)       — implements the code
#   3. Validator (Codex)    — runs PHPStan + CS, fixes issues
#   4. Tester (Codex)       — runs tests, fixes failures
#   5. Documenter (Sonnet)  — writes documentation
#
# Usage:
#   ./scripts/pipeline.sh "Add streaming support to A2A gateway"
#   ./scripts/pipeline.sh --skip-architect "implement change add-a2a-streaming"
#   ./scripts/pipeline.sh --from coder "Continue implementing add-a2a-streaming"
#   ./scripts/pipeline.sh --only validator "Run PHPStan on core"
#   ./scripts/pipeline.sh --audit "Add feature X"   # adds auditor as 6th agent
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
NC='\033[0m'

# Agent order
AGENTS=(architect coder validator tester documenter)

# Timeouts per agent (seconds, override via env)
PIPELINE_TIMEOUT_ARCHITECT="${PIPELINE_TIMEOUT_ARCHITECT:-2700}"   # 45 min
PIPELINE_TIMEOUT_CODER="${PIPELINE_TIMEOUT_CODER:-3600}"           # 60 min
PIPELINE_TIMEOUT_VALIDATOR="${PIPELINE_TIMEOUT_VALIDATOR:-1200}"   # 20 min
PIPELINE_TIMEOUT_TESTER="${PIPELINE_TIMEOUT_TESTER:-1800}"         # 30 min
PIPELINE_TIMEOUT_DOCUMENTER="${PIPELINE_TIMEOUT_DOCUMENTER:-900}"  # 15 min
PIPELINE_TIMEOUT_AUDITOR="${PIPELINE_TIMEOUT_AUDITOR:-1200}"       # 20 min

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
FALLBACK_ARCHITECT="${PIPELINE_FALLBACK_ARCHITECT:-anthropic/claude-sonnet-4-6,openai/gpt-5.3-codex,free,cheap}"
FALLBACK_CODER="${PIPELINE_FALLBACK_CODER:-openai/gpt-5.3-codex,anthropic/claude-opus-4-6,free,cheap}"
FALLBACK_VALIDATOR="${PIPELINE_FALLBACK_VALIDATOR:-anthropic/claude-sonnet-4-6,openai/codex-mini-latest,free,cheap}"
FALLBACK_TESTER="${PIPELINE_FALLBACK_TESTER:-anthropic/claude-sonnet-4-6,openai/codex-mini-latest,free,cheap}"
FALLBACK_DOCUMENTER="${PIPELINE_FALLBACK_DOCUMENTER:-anthropic/claude-opus-4-6,free,cheap}"
FALLBACK_AUDITOR="${PIPELINE_FALLBACK_AUDITOR:-anthropic/claude-sonnet-4-6,free,cheap}"

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
  --audit             Add auditor as 6th quality gate agent
  --webhook <url>     POST JSON summary to webhook on completion/failure
  --telegram          Send status updates via Telegram bot
  --no-commit         Skip auto-commits between agents
  -h, --help          Show this help

Agents: architect, coder, validator, tester, documenter

Timeouts (override via env):
  PIPELINE_TIMEOUT_ARCHITECT=1800   (30 min)
  PIPELINE_TIMEOUT_CODER=3600      (60 min)
  PIPELINE_TIMEOUT_VALIDATOR=1200  (20 min)
  PIPELINE_TIMEOUT_TESTER=1800    (30 min)
  PIPELINE_TIMEOUT_DOCUMENTER=900  (15 min)
  PIPELINE_MAX_RETRIES=2
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
  PIPELINE_CHEAP_MODELS          (default: deepseek-v3.2,gemini-3.1-flash-lite)
  PIPELINE_FREE_MODELS           (default: big-pickle,gpt-5-nano,minimax-m2.5-free)

Examples:
  ./scripts/pipeline.sh "Add streaming support to A2A"
  ./scripts/pipeline.sh --skip-architect "Implement openspec change add-a2a-streaming"
  ./scripts/pipeline.sh --from validator "Fix validation issues"
  ./scripts/pipeline.sh --only tester "Run tests for hello-agent"
  ./scripts/pipeline.sh --audit "Add feature with quality gate"
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

  # 2. Docker daemon
  if ! docker info &>/dev/null; then
    echo -e "  ${RED}✗ Docker daemon not running${NC}"
    errors=$((errors + 1))
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

  # Add auditor if enabled
  if [[ "$ENABLE_AUDIT" == true ]]; then
    agent_list+=(auditor)
  fi

  if [[ -n "$ONLY_AGENT" ]]; then
    echo "$ONLY_AGENT"
    return
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

ARTIFACTS_BASE="$REPO_ROOT/tasks/artifacts"

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

  mkdir -p "$ARTIFACTS_DIR"

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

# Write checkpoint after agent completes
write_checkpoint() {
  local agent="$1"
  local status="$2"
  local duration="$3"
  local commit_hash="${4:-}"

  [[ -f "$CHECKPOINT_FILE" ]] || return 0

  # Use python3 to safely update JSON
  python3 -c "
import json, sys
with open('$CHECKPOINT_FILE', 'r') as f:
    data = json.load(f)
data['agents']['$agent'] = {
    'status': '$status',
    'duration': $duration,
    'commit': '$commit_hash',
    'finished': '$(date '+%Y-%m-%d %H:%M:%S')'
}
with open('$CHECKPOINT_FILE', 'w') as f:
    json.dump(data, f, indent=2)
" 2>/dev/null || true
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
  fi
}

# Read checkpoint and determine which agent to resume from
get_resume_agent() {
  [[ -f "$CHECKPOINT_FILE" ]] || return

  python3 -c "
import json
agents_order = ['architect', 'coder', 'validator', 'tester', 'documenter']
with open('$CHECKPOINT_FILE', 'r') as f:
    data = json.load(f)
completed = data.get('agents', {})
for agent in agents_order:
    info = completed.get(agent, {})
    if info.get('status') != 'done':
        print(agent)
        break
" 2>/dev/null || true
}

# Print checkpoint summary
print_checkpoint_summary() {
  [[ -f "$CHECKPOINT_FILE" ]] || return

  python3 -c "
import json
with open('$CHECKPOINT_FILE', 'r') as f:
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
" 2>/dev/null || true
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
  grep -oP '^model:\s*\K.*' "$agent_file" 2>/dev/null | tr -d ' '
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

    # Run with timeout
    # NOTE: We cd into REPO_ROOT instead of using --dir because opencode
    # registers git worktrees as "sandboxes" with restricted permissions
    # when passed via --dir. Running from CWD lets opencode detect the
    # worktree as an independent project with full file access.
    local exit_code=0
    if command -v timeout &>/dev/null; then
      (cd "$REPO_ROOT" && timeout "$agent_timeout" opencode run \
        --agent "$agent" \
        "$message") \
        2>&1 | tee "$log_file" || exit_code=$?
    else
      (cd "$REPO_ROOT" && opencode run \
        --agent "$agent" \
        "$message") \
        2>&1 | tee "$log_file" || exit_code=$?
    fi

    # Check result
    if [[ $exit_code -eq 0 ]]; then
      echo ""
      echo -e "${GREEN}✓ Agent '${agent}' completed successfully${NC}"
      # Restore original model if we swapped
      restore_agent_model "$agent" "$original_model"
      return 0
    elif [[ $exit_code -eq 124 ]]; then
      echo -e "${RED}✗ Agent '${agent}' timed out after ${timeout_min} min${NC}"
      restore_agent_model "$agent" "$original_model"
      return 1
    else
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
    architect)
      cat << PROMPT
Task: ${TASK_MESSAGE}

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

1. Read \`.opencode/pipeline/handoff.md\` for context from the architect
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

## Handoff

Update \`.opencode/pipeline/handoff.md\` — Coder section:
- List every file created or modified
- List any migration files created
- Note deviations from the spec (with reasoning)
PROMPT
      ;;
    validator)
      cat << PROMPT
Task: ${TASK_MESSAGE}

## Instructions

1. Read \`.opencode/pipeline/handoff.md\` to know which apps were changed
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
Task: ${TASK_MESSAGE}

## Instructions

1. Read \`.opencode/pipeline/handoff.md\` to know which apps and files were changed
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
Task: ${TASK_MESSAGE}

## Instructions

1. Read \`.opencode/pipeline/handoff.md\` for the full pipeline context
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
      cat << PROMPT
Task: Audit the changes from pipeline — ${TASK_MESSAGE}

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

## Documenter

- **Status**: pending
- **Docs created/updated**: —

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

  # Initialize handoff
  init_handoff

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

  # Get agents to run
  local agents_to_run
  agents_to_run=$(get_agents_to_run)

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

  # Tracking: agent_name:status:duration
  local report_lines=""
  local pipeline_start
  pipeline_start=$(date +%s)

  # Run each agent
  local failed=false
  local failed_agent=""
  for agent in $agents_to_run; do
    local prompt
    prompt=$(build_prompt "$agent")

    local agent_start
    agent_start=$(date +%s)

    # Log file for this agent run
    local agent_log="$LOG_DIR/${TIMESTAMP}_${agent}.log"

    if run_agent "$agent" "$prompt"; then
      local agent_dur=$(( $(date +%s) - agent_start ))
      report_lines="${report_lines}| ${agent} | ✓ pass | ${agent_dur}s |
"

      send_telegram "✅ <b>${agent}</b> completed (${agent_dur}s)
📋 <i>${TASK_MESSAGE}</i>"

      # Auto-commit after each successful agent
      commit_agent_work "$agent" "$task_slug"
      local commit_hash
      commit_hash=$(git -C "$REPO_ROOT" rev-parse --short HEAD 2>/dev/null || echo "")

      # Save checkpoint & artifacts
      write_checkpoint "$agent" "done" "$agent_dur" "$commit_hash"
      save_agent_artifact "$agent" "$agent_log"

      # Stage gate: verify coder produced actual code changes
      if [[ "$agent" == "coder" ]]; then
        if ! verify_coder_output; then
          local agent_dur_fail=$(( $(date +%s) - agent_start ))
          report_lines="${report_lines}| ${agent} | ✗ no-code | ${agent_dur}s |
"
          write_checkpoint "$agent" "failed-no-code" "$agent_dur_fail" ""
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
      report_lines="${report_lines}| ${agent} | ✗ fail | ${agent_dur}s |
"

      # Save failed checkpoint & artifact
      write_checkpoint "$agent" "failed" "$agent_dur" ""
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
    echo "| Agent | Status | Duration |"
    echo "|-------|--------|----------|"
    echo -n "$report_lines"
    echo ""
    echo "## Cost"
    echo '```'
    opencode stats 2>/dev/null || echo "(stats unavailable)"
    echo '```'
  } > "$report_file"

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

  # Final status
  echo ""
  echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  if $failed; then
    echo -e "${RED}Pipeline FAILED at agent: ${failed_agent}${NC}"
    echo -e "${BLUE}Report:${NC}  ${report_file}"
    echo -e "${YELLOW}Logs:${NC}    ${LOG_DIR}/${TIMESTAMP}_*.log${NC}"
    exit 1
  else
    echo -e "${GREEN}Pipeline COMPLETED in $(( total_duration / 60 )) min${NC}"
    echo -e "${BLUE}Branch:${NC}  ${branch}"
    echo -e "${BLUE}Report:${NC}  ${report_file}"
    echo -e "${BLUE}Handoff:${NC} ${HANDOFF_FILE}"
    echo -e "${BLUE}Logs:${NC}    ${LOG_DIR}/${TIMESTAMP}_*.log${NC}"
  fi
}

main
