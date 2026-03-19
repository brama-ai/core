#!/usr/bin/env bash
# shellcheck disable=SC2034
#
# Interactive pipeline monitor with tab-based TUI.
# Version: 0.15.2
#
MONITOR_VERSION="0.16.0"
# Usage:
#   ./builder/monitor/pipeline-monitor.sh              # auto-detect tasks/ folder
#   ./builder/monitor/pipeline-monitor.sh tasks/       # monitor specific tasks folder
#
# Tabs:
#   [1] Overview   — task statuses, progress bar, timing
#   [2] Activity   — timeline of task & agent events
#   [3] Worker 1   — live log tail for worker-1
#   ...
#
# Keys:
#   ←/→ or 1-9  Switch tabs
#   ↑/↓         Select task (Overview tab)
#   Enter       View selected task detail
#   Esc/Bksp    Back to task list
#   s           Start batch / start extra worker for selected task (manual)
#
# Auto-start:
#   Workers are launched automatically when tasks appear in todo/.
#   Set MONITOR_AUTOSTART=false to disable.
#   Set MONITOR_WORKERS=N to control max parallel workers (default: 1).
#   f           Retry failed (move failed→todo, delete branches, start)
#   k           Kill running batch
#   x           Stop selected in-progress task
#   +           Raise priority of selected waiting task
#   -           Lower priority of selected waiting task
#   l           View logs for selected failed/in-progress task
#   d           Delete selected waiting/failed task (with files)
#   a           Archive all completed tasks
#   r           Refresh
#   q/Ctrl-C    Quit (or back from log/detail view)
#
# Task priority:
#   Tasks in todo/ are sorted by priority. Priority is set via a comment
#   in the first line of the .md file:
#     <!-- priority: 5 -->
#   Higher number = higher priority. Default priority = 1.
#   Use [+] and [-] keys to adjust priority of the selected todo task.
#
set -uo pipefail
shopt -s extglob

REPO_ROOT="$(cd "$(dirname "$(readlink -f "$0" 2>/dev/null || realpath "$0" 2>/dev/null || echo "$0")")/../.." && pwd)"
TASK_SOURCE="${1:-$REPO_ROOT/builder/tasks}"
# Ensure all lifecycle folders exist (builder/tasks/ is gitignored)
mkdir -p "$TASK_SOURCE/todo" "$TASK_SOURCE/in-progress" "$TASK_SOURCE/done" "$TASK_SOURCE/failed" "$TASK_SOURCE/summary" "$TASK_SOURCE/artifacts" "$TASK_SOURCE/archive"
if [[ -d "$REPO_ROOT/.pipeline-worktrees" ]]; then
  WORKTREE_BASE="$REPO_ROOT/.pipeline-worktrees"
else
  WORKTREE_BASE="$REPO_ROOT/.opencode/pipeline/worktrees"
fi
LOG_DIR="$REPO_ROOT/.opencode/pipeline/logs"
REPORT_DIR="$REPO_ROOT/.opencode/pipeline/reports"
LOG_RETENTION_DAYS="${MONITOR_LOG_RETENTION:-7}"  # auto-cleanup logs older than N days

# Load .env.local for API keys (if exists)
if [[ -f "$REPO_ROOT/.env.local" ]]; then
  # shellcheck disable=SC1091
  set -a; source "$REPO_ROOT/.env.local" 2>/dev/null; set +a
fi

# ── Colors ────────────────────────────────────────────────────────────
if command -v tput &>/dev/null && [[ -t 1 ]]; then
  BOLD=$(tput bold); DIM=$(tput dim); REV=$(tput rev); RESET=$(tput sgr0)
  RED=$(tput setaf 1); GREEN=$(tput setaf 2); YELLOW=$(tput setaf 3)
  BLUE=$(tput setaf 4); MAGENTA=$(tput setaf 5); CYAN=$(tput setaf 6)
  WHITE=$(tput setaf 7)
else
  BOLD='' DIM='' REV='' RESET=''
  RED='' GREEN='' YELLOW='' BLUE='' MAGENTA='' CYAN='' WHITE=''
fi

# ── State ─────────────────────────────────────────────────────────────
CURRENT_TAB=1
MAX_TABS=2  # Overview + Logs minimum
SELECTED_IDX=0
DETAIL_MODE=false
DETAIL_FILE=""
LOG_VIEW_MODE=false
LOG_VIEW_FILE=""
ACTION_MSG=""
ACTIVE_WORKER_COUNT=0
REFRESH_INTERVAL=3
WORKERS="${MONITOR_WORKERS:-1}"
AUTOSTART="${MONITOR_AUTOSTART:-true}"  # auto-launch workers when tasks are waiting
AUTOSTART_COOLDOWN=5  # seconds between auto-start attempts
AUTOSTART_LAST=0      # epoch of last auto-start action

# ── Cache control ────────────────────────────────────────────────────
RENDER_CYCLE=0
CACHE_TTL=2  # rebuild expensive data every N render cycles
FORCE_REBUILD=true  # set true after user actions
CACHED_WORKER_HINTS=()  # indexed same as DETECTED_WORKERS
CACHED_LOG_MAP=()       # "taskfname|logpath" entries
CACHED_TODO_COUNT=0
CACHED_INPROG_COUNT=0
CACHED_DONE_COUNT=0
CACHED_FAILED_COUNT=0

cache_expired() { [[ "$FORCE_REBUILD" == true ]] || (( RENDER_CYCLE % CACHE_TTL == 0 )); }
invalidate_cache() { FORCE_REBUILD=true; }

# Worker list (populated by update_worker_state)
DETECTED_WORKERS=()

# Task list arrays (populated by build_task_list)
ALL_TASKS_FILES=()
ALL_TASKS_TITLES=()
ALL_TASKS_STATES=()
ALL_TASKS_COUNT=0

# ── Buffer renderer (flicker-free) ───────────────────────────────────
ESC=$'\033'
CLR="${ESC}[2K"
RENDER_BUF=""
PREV_LINE_COUNT=0

buf_reset() { RENDER_BUF=""; }
buf_line()  { RENDER_BUF+="${CLR}${1}"$'\n'; }
buf_flush() {
  local cur_lines
  cur_lines=$(printf '%s' "$RENDER_BUF" | wc -l | tr -d ' ')
  printf '%s[H' "$ESC"
  printf '%s' "$RENDER_BUF"
  local i
  for ((i=cur_lines; i<PREV_LINE_COUNT; i++)); do
    printf '%s\n' "$CLR"
  done
  if [[ $cur_lines -lt $PREV_LINE_COUNT ]]; then
    printf '%s[%dA' "$ESC" "$((PREV_LINE_COUNT - cur_lines))"
  fi
  PREV_LINE_COUNT=$cur_lines
}

# ── Helpers ───────────────────────────────────────────────────────────
count_files() {
  local dir="$1"
  if [[ -d "$dir" ]]; then
    local files=("$dir"/*.md)
    [[ -e "${files[0]}" ]] && echo "${#files[@]}" || echo "0"
  else
    echo "0"
  fi
}

# Count all four task dirs in one pass (no subshells)
count_all_task_dirs() {
  cache_expired || return 0
  local d f
  for d in todo in-progress done failed; do
    local count=0
    if [[ -d "$TASK_SOURCE/$d" ]]; then
      for f in "$TASK_SOURCE/$d"/*.md; do
        [[ -e "$f" ]] && (( count++ ))
      done
    fi
    case "$d" in
      todo)        CACHED_TODO_COUNT=$count ;;
      in-progress) CACHED_INPROG_COUNT=$count ;;
      done)        CACHED_DONE_COUNT=$count ;;
      failed)      CACHED_FAILED_COUNT=$count ;;
    esac
  done
}

# ── Log auto-cleanup (runs once on startup) ──────────────────────────
LOG_CLEANUP_DONE=false
cleanup_old_logs() {
  [[ "$LOG_CLEANUP_DONE" == true ]] && return
  LOG_CLEANUP_DONE=true
  [[ "$LOG_RETENTION_DAYS" -le 0 ]] 2>/dev/null && return
  local cleaned=0
  # Clean old .log, .meta.json files from main log dir
  if [[ -d "$LOG_DIR" ]]; then
    while IFS= read -r f; do
      [[ -n "$f" ]] && rm -f "$f" && (( cleaned++ ))
    done < <(find "$LOG_DIR" -maxdepth 1 \( -name '*.log' -o -name '*.meta.json' \) -mtime +"$LOG_RETENTION_DAYS" 2>/dev/null)
  fi
  # Clean old batch reports
  if [[ -d "$REPORT_DIR" ]]; then
    while IFS= read -r f; do
      [[ -n "$f" ]] && rm -f "$f" && (( cleaned++ ))
    done < <(find "$REPORT_DIR" -maxdepth 1 -name 'batch_*.md' -mtime +"$LOG_RETENTION_DAYS" 2>/dev/null)
  fi
  [[ $cleaned -gt 0 ]] && ACTION_MSG="${DIM}Cleaned $cleaned old log/report files (>${LOG_RETENTION_DAYS}d)${RESET}"
}

# ── Provider balance & token stats ───────────────────────────────────
PROVIDER_BALANCE_CACHE=""
PROVIDER_BALANCE_CYCLE=0
PROVIDER_BALANCE_TTL=20  # refresh every ~60s (20 cycles × 3s)

# ── Agent model config cache ────────────────────────────────────────
CACHED_PROVIDER_LINE=""
PROVIDER_LINE_CYCLE=-1
PROVIDER_LINE_TTL=10  # refresh every ~30s

CACHED_TOTAL_INPUT=0
CACHED_TOTAL_OUTPUT=0
CACHED_TOTAL_CACHE_R=0
CACHED_TOTAL_CACHE_W=0
CACHED_TOTAL_COST="0"
CACHED_ANTHROPIC_COST="0"
CACHED_ANTHROPIC_MSGS=0
CACHED_OPENAI_COST="0"
CACHED_OPENAI_MSGS=0
CACHED_FREE_COST="0"
CACHED_FREE_MSGS=0
OPENCODE_DB=""
# Locate opencode DB once
if command -v opencode &>/dev/null; then
  OPENCODE_DB=$(opencode db path 2>/dev/null || true)
fi

# Known pricing per 1M tokens (approximate, USD)
# Format: input/output per 1M tokens
_estimate_cost() {
  local in_tok="$1" out_tok="$2" cache_r="$3" cache_w="$4"
  # Average blended rate: ~$3/1M input, ~$15/1M output, ~$0.30/1M cache_read, ~$3.75/1M cache_write
  # These are approximate for Claude Sonnet-class models via OpenRouter
  awk "BEGIN { printf \"%.2f\", ($in_tok * 3 + $out_tok * 15 + $cache_r * 0.30 + $cache_w * 3.75) / 1000000 }"
}

query_openrouter_balance() {
  # Only refresh every N render cycles
  if [[ $((RENDER_CYCLE - PROVIDER_BALANCE_CYCLE)) -lt $PROVIDER_BALANCE_TTL && -n "$PROVIDER_BALANCE_CACHE" ]]; then
    return
  fi
  PROVIDER_BALANCE_CYCLE=$RENDER_CYCLE
  PROVIDER_BALANCE_CACHE=""

  if [[ -n "${OPENROUTER_API_KEY:-}" ]]; then
    local resp
    resp=$(curl -s --max-time 3 "https://openrouter.ai/api/v1/auth/key" \
      -H "Authorization: Bearer $OPENROUTER_API_KEY" 2>/dev/null) || return
    if [[ -n "$resp" ]]; then
      local usage limit remaining pct
      usage=$(echo "$resp" | awk -F'"usage":' '{print $2}' | awk -F'[,}]' '{print $1}' | tr -d ' ')
      limit=$(echo "$resp" | awk -F'"limit":' '{print $2}' | awk -F'[,}]' '{print $1}' | tr -d ' ')
      if [[ -n "$limit" && "$limit" != "null" && -n "$usage" ]]; then
        remaining=$(awk "BEGIN { printf \"%.2f\", $limit - $usage }")
        pct=$(awk "BEGIN { if ($limit > 0) printf \"%.0f\", ($usage / $limit) * 100; else print \"0\" }")
        local color="$GREEN"
        (( pct > 70 )) && color="$YELLOW"
        (( pct > 90 )) && color="$RED"
        PROVIDER_BALANCE_CACHE="${DIM}OpenRouter:${RESET} ${color}${pct}%${RESET} used (\$${usage}/\$${limit})"
      fi
    fi
  fi
}

build_provider_line() {
  if [[ $((RENDER_CYCLE - PROVIDER_LINE_CYCLE)) -lt $PROVIDER_LINE_TTL && -n "$CACHED_PROVIDER_LINE" ]]; then
    return
  fi
  PROVIDER_LINE_CYCLE=$RENDER_CYCLE
  CACHED_PROVIDER_LINE=""

  local agent_dir="$REPO_ROOT/.opencode/agents"
  [[ -d "$agent_dir" ]] || return

  # Collect unique providers → agent list
  local anthropic_agents="" openai_agents="" other_agents=""
  local f agent model provider
  for f in "$agent_dir"/*.md; do
    [[ -f "$f" ]] || continue
    agent="${f##*/}"; agent="${agent%.md}"
    model=""
    while IFS= read -r line; do
      case "$line" in
        model:*) model="${line#model:}"; model="${model# }"; break ;;
        ---) ;; # frontmatter delimiter
        *) [[ -n "$model" ]] && break ;;  # past frontmatter
      esac
    done < "$f"
    [[ -z "$model" ]] && continue
    # Extract short model name (after last /)
    local short="${model##*/}"
    # Group by provider prefix
    case "$model" in
      anthropic/*)
        [[ -n "$anthropic_agents" ]] && anthropic_agents+=","
        anthropic_agents+="${agent}:${short}" ;;
      openai/*)
        [[ -n "$openai_agents" ]] && openai_agents+=","
        openai_agents+="${agent}:${short}" ;;
      *)
        [[ -n "$other_agents" ]] && other_agents+=","
        other_agents+="${agent}:${short}" ;;
    esac
  done

  local parts=""
  if [[ -n "$anthropic_agents" ]]; then
    parts+="${MAGENTA}Claude${RESET}${DIM}[${anthropic_agents}]${RESET}"
  fi
  if [[ -n "$openai_agents" ]]; then
    [[ -n "$parts" ]] && parts+="  "
    parts+="${GREEN}Codex${RESET}${DIM}[${openai_agents}]${RESET}"
  fi
  if [[ -n "$other_agents" ]]; then
    [[ -n "$parts" ]] && parts+="  "
    parts+="${YELLOW}Other${RESET}${DIM}[${other_agents}]${RESET}"
  fi
  CACHED_PROVIDER_LINE="$parts"
}

aggregate_batch_tokens() {
  cache_expired || return 0
  CACHED_TOTAL_INPUT=0; CACHED_TOTAL_OUTPUT=0; CACHED_TOTAL_CACHE_R=0; CACHED_TOTAL_CACHE_W=0
  CACHED_ANTHROPIC_COST="0"; CACHED_ANTHROPIC_MSGS=0
  CACHED_OPENAI_COST="0"; CACHED_OPENAI_MSGS=0
  CACHED_FREE_COST="0"; CACHED_FREE_MSGS=0

  # Primary source: opencode SQLite DB (accurate per-provider data)
  if [[ -n "$OPENCODE_DB" && -f "$OPENCODE_DB" ]]; then
    _aggregate_from_opencode_db
    return
  fi

  # Fallback: parse .meta.json files from pipeline logs
  _aggregate_from_meta_files
}

_aggregate_from_opencode_db() {
  local rows
  rows=$(sqlite3 -separator $'\t' "$OPENCODE_DB" "
    SELECT
      COALESCE(json_extract(data, '\$.providerID'), 'unknown'),
      COUNT(*),
      COALESCE(SUM(json_extract(data, '\$.tokens.input')), 0),
      COALESCE(SUM(json_extract(data, '\$.tokens.output')), 0),
      COALESCE(SUM(json_extract(data, '\$.tokens.cache.read')), 0),
      COALESCE(SUM(json_extract(data, '\$.tokens.cache.write')), 0)
    FROM message
    WHERE json_extract(data, '\$.role') = 'assistant'
      AND time_created > (strftime('%s','now') - 7*86400) * 1000
    GROUP BY json_extract(data, '\$.providerID')
  " 2>/dev/null) || return

  local provider msgs inp outp cr cw
  while IFS=$'\t' read -r provider msgs inp outp cr cw; do
    [[ -z "$provider" ]] && continue
    CACHED_TOTAL_INPUT=$((CACHED_TOTAL_INPUT + inp))
    CACHED_TOTAL_OUTPUT=$((CACHED_TOTAL_OUTPUT + outp))
    CACHED_TOTAL_CACHE_R=$((CACHED_TOTAL_CACHE_R + cr))
    CACHED_TOTAL_CACHE_W=$((CACHED_TOTAL_CACHE_W + cw))
    local cost
    cost=$(_estimate_cost "$inp" "$outp" "$cr" "$cw")
    case "$provider" in
      anthropic)
        CACHED_ANTHROPIC_COST="$cost"; CACHED_ANTHROPIC_MSGS=$msgs ;;
      openai)
        CACHED_OPENAI_COST="$cost"; CACHED_OPENAI_MSGS=$msgs ;;
      opencode|*)
        CACHED_FREE_COST="$cost"; CACHED_FREE_MSGS=$msgs ;;
    esac
  done <<< "$rows"
  CACHED_TOTAL_COST=$(_estimate_cost "$CACHED_TOTAL_INPUT" "$CACHED_TOTAL_OUTPUT" "$CACHED_TOTAL_CACHE_R" "$CACHED_TOTAL_CACHE_W")
}

_aggregate_from_meta_files() {
  local meta_files=()
  if [[ -d "$LOG_DIR" ]]; then
    for f in "$LOG_DIR"/*.meta.json; do
      [[ -f "$f" ]] && meta_files+=("$f")
    done
  fi
  local wt_log
  for wt_log in "$WORKTREE_BASE"/worker-*/.opencode/pipeline/logs; do
    [[ -d "$wt_log" ]] || continue
    for f in "$wt_log"/*.meta.json; do
      [[ -f "$f" ]] && meta_files+=("$f")
    done
  done
  [[ ${#meta_files[@]} -eq 0 ]] && return

  local totals
  totals=$(awk '
    /"input_tokens"/    { gsub(/[^0-9]/, ""); input += $0 }
    /"output_tokens"/   { gsub(/[^0-9]/, ""); output += $0 }
    /"cache_read"/      { gsub(/[^0-9]/, ""); cache_r += $0 }
    /"cache_write"/     { gsub(/[^0-9]/, ""); cache_w += $0 }
    END { printf "%d\t%d\t%d\t%d", input, output, cache_r, cache_w }
  ' "${meta_files[@]}" 2>/dev/null) || return
  IFS=$'\t' read -r CACHED_TOTAL_INPUT CACHED_TOTAL_OUTPUT CACHED_TOTAL_CACHE_R CACHED_TOTAL_CACHE_W <<< "$totals"
  CACHED_TOTAL_COST=$(_estimate_cost "$CACHED_TOTAL_INPUT" "$CACHED_TOTAL_OUTPUT" "$CACHED_TOTAL_CACHE_R" "$CACHED_TOTAL_CACHE_W")
}

_format_tokens() {
  local n="$1"
  if (( n >= 1000000 )); then
    awk "BEGIN { printf \"%.1fM\", $n / 1000000 }"
  elif (( n >= 1000 )); then
    awk "BEGIN { printf \"%.1fK\", $n / 1000 }"
  else
    echo "$n"
  fi
}

render_cost_bar() {
  # Line 1: provider usage from opencode DB (7d)
  local prov_line=""
  if (( CACHED_ANTHROPIC_MSGS > 0 )); then
    prov_line+="  ${MAGENTA}Claude${RESET}${DIM}(\$100)${RESET} ${CACHED_ANTHROPIC_MSGS}msg ≈\$${CACHED_ANTHROPIC_COST}"
  fi
  if (( CACHED_OPENAI_MSGS > 0 )); then
    prov_line+="  ${GREEN}Codex${RESET}${DIM}(\$20)${RESET} ${CACHED_OPENAI_MSGS}msg ≈\$${CACHED_OPENAI_COST}"
  fi
  if (( CACHED_FREE_MSGS > 0 )); then
    prov_line+="  ${YELLOW}Free${RESET} ${CACHED_FREE_MSGS}msg"
  fi
  if [[ -n "$prov_line" ]]; then
    buf_line "  ${DIM}Providers (7d):${RESET}${prov_line}"
  fi
  # Line 2: agent model config
  if [[ -n "$CACHED_PROVIDER_LINE" ]]; then
    buf_line "  ${DIM}Models:${RESET} $CACHED_PROVIDER_LINE"
  fi
  # Line 3: tokens + OpenRouter balance
  local out=""
  if (( CACHED_TOTAL_INPUT + CACHED_TOTAL_OUTPUT > 0 )); then
    out+="  ${DIM}Tokens:${RESET} "
    out+="${CYAN}↓$(_format_tokens $CACHED_TOTAL_INPUT)${RESET}"
    out+=" ${CYAN}↑$(_format_tokens $CACHED_TOTAL_OUTPUT)${RESET}"
    out+=" ${DIM}cache:$(_format_tokens $CACHED_TOTAL_CACHE_R)r/$(_format_tokens $CACHED_TOTAL_CACHE_W)w${RESET}"
    out+="  ${DIM}≈\$${CACHED_TOTAL_COST}${RESET}"
  fi
  if [[ -n "$PROVIDER_BALANCE_CACHE" ]]; then
    [[ -n "$out" ]] && out+="  │  "
    out+="$PROVIDER_BALANCE_CACHE"
  fi
  [[ -n "$out" ]] && buf_line "$out"
}

get_terminal_size() {
  TERM_ROWS=$(tput lines 2>/dev/null || echo 24)
  TERM_COLS=$(tput cols 2>/dev/null || echo 80)
}

hline() { printf '%*s' "$TERM_COLS" '' | tr ' ' "${1:-─}"; }

progress_bar_str() {
  local done_n="$1" total="$2"
  local width=$((TERM_COLS - 20))
  [[ $width -lt 10 ]] && width=10
  if [[ $total -eq 0 ]]; then
    printf "[%*s] 0/0" "$width" ""
    return
  fi
  local filled=$(( done_n * width / total ))
  local empty=$(( width - filled ))
  printf "${GREEN}["
  printf '%*s' "$filled" '' | tr ' ' '█'
  printf '%*s' "$empty" '' | tr ' ' '░'
  printf "]${RESET} %d/%d" "$done_n" "$total"
}

format_duration() {
  local secs="$1"
  if [[ $secs -ge 3600 ]]; then
    printf "%dh %dm %ds" $((secs/3600)) $((secs%3600/60)) $((secs%60))
  elif [[ $secs -ge 60 ]]; then
    printf "%dm %ds" $((secs/60)) $((secs%60))
  else
    printf "%ds" "$secs"
  fi
}

is_batch_running() { pgrep -f 'pipeline-batch.sh' &>/dev/null; }

# ── Priority ──────────────────────────────────────────────────────────
get_priority() {
  local prio
  prio=$(head -1 "$1" 2>/dev/null | sed -n 's/.*<!-- *priority: *\([0-9]*\) *-->.*/\1/p')
  echo "${prio:-1}"
}

set_priority() {
  local file="$1" prio="$2"
  local first_line
  first_line=$(head -1 "$file" 2>/dev/null)
  if [[ "$first_line" =~ \<!--\ *priority: ]]; then
    sed -i.bak "1s/<!-- *priority: *[0-9]* *-->/<!-- priority: ${prio} -->/" "$file"
    rm -f "${file}.bak"
  else
    local tmp; tmp=$(mktemp)
    echo "<!-- priority: ${prio} -->" > "$tmp"
    cat "$file" >> "$tmp"
    mv "$tmp" "$file"
  fi
}

list_todo_sorted() {
  local todo_dir="$TASK_SOURCE/todo"
  [[ -d "$todo_dir" ]] || return
  local entries=() ecount=0
  while IFS= read -r f; do
    [[ -f "$f" ]] || continue
    local prio; prio=$(get_priority "$f")
    entries+=("$(printf '%03d|%s' "$prio" "$f")")
    ecount=$((ecount + 1))
  done < <(find "$todo_dir" -maxdepth 1 -name '*.md' 2>/dev/null | sort)
  [[ $ecount -gt 0 ]] && printf '%s\n' "${entries[@]}" | sort -t'|' -k1 -rn | cut -d'|' -f2
}

# ── Worker detection ──────────────────────────────────────────────────
_worker_is_alive() {
  # Check if any process is using this worktree (pipeline.sh, opencode, etc.)
  local worker="$1"
  local wt_path="$WORKTREE_BASE/$worker"
  pgrep -f "pipeline.sh.*${worker}" &>/dev/null && return 0
  pgrep -f "${wt_path}" &>/dev/null && return 0
  # Also alive if batch is running and the worktree was recently modified (< 60s)
  if is_batch_running; then
    local mtime now
    mtime=$(stat -f%m "$wt_path" 2>/dev/null || stat -c%Y "$wt_path" 2>/dev/null || echo 0)
    now=$(date +%s)
    (( now - mtime < 60 )) && return 0
  fi
  return 1
}

detect_workers() {
  # Re-evaluate worktree base each time — batch may create it after monitor starts
  if [[ -d "$REPO_ROOT/.pipeline-worktrees" ]]; then
    WORKTREE_BASE="$REPO_ROOT/.pipeline-worktrees"
  else
    WORKTREE_BASE="$REPO_ROOT/.opencode/pipeline/worktrees"
  fi
  local workers=() wcount=0
  if [[ -d "$WORKTREE_BASE" ]]; then
    for wt in "$WORKTREE_BASE"/worker-*; do
      [[ -d "$wt" ]] || continue
      local wname; wname=$(basename "$wt")
      # Only show workers that are actually active
      _worker_is_alive "$wname" && { workers+=("$wname"); wcount=$((wcount + 1)); }
    done
  fi
  [[ $wcount -gt 0 ]] && echo "${workers[*]}"
}

get_worker_active_task() {
  local worker="$1"
  local wt_path="$WORKTREE_BASE/$worker"
  local pid
  pid=$(pgrep -f "pipeline.sh.*${worker}" 2>/dev/null | head -1 || true)
  [[ -z "$pid" ]] && pid=$(pgrep -f "${wt_path}" 2>/dev/null | head -1 || true)
  if [[ -n "$pid" ]]; then
    local task_file
    task_file=$(ps -o args= -p "$pid" 2>/dev/null | grep -oE '\-\-task-file [^ ]+' | awk '{print $2}')
    if [[ -n "$task_file" && -f "$task_file" ]]; then
      grep -m1 '^# ' "$task_file" 2>/dev/null | sed 's/^# //'
      return
    fi
  fi
  local wt_log_dir="$wt_path/.opencode/pipeline/logs"
  local log_file=""
  [[ -d "$wt_log_dir" ]] && log_file=$(ls -t "$wt_log_dir"/*.log 2>/dev/null | head -1 || true)
  if [[ -n "$log_file" ]]; then
    echo "[$(basename "$log_file" .log | sed 's/^[0-9_]*//')]"
  fi
}

# ── Task list builder ─────────────────────────────────────────────────
# Extract title from a task file using bash read (avoids grep+sed subshell)
_extract_title() {
  local file="$1" line=""
  while IFS= read -r line; do
    if [[ "$line" == "# "* ]]; then
      printf '%s' "${line#\# }"
      return
    fi
  done < "$file" 2>/dev/null
  basename "$file" .md
}

build_task_list() {
  if ! cache_expired; then return; fi
  ALL_TASKS_FILES=(); ALL_TASKS_TITLES=(); ALL_TASKS_STATES=()
  local dirs=("in-progress" "done" "failed") d f title
  for d in "${dirs[@]}"; do
    [[ -d "$TASK_SOURCE/$d" ]] || continue
    for f in "$TASK_SOURCE/$d"/*.md; do
      [[ -f "$f" ]] || continue
      title=$(_extract_title "$f")
      ALL_TASKS_FILES+=("$f")
      ALL_TASKS_TITLES+=("$title")
      ALL_TASKS_STATES+=("$d")
    done
  done
  while IFS= read -r f; do
    [[ -f "$f" ]] || continue
    title=$(_extract_title "$f")
    ALL_TASKS_FILES+=("$f")
    ALL_TASKS_TITLES+=("$title")
    ALL_TASKS_STATES+=("todo:$(get_priority "$f")")
  done < <(list_todo_sorted)
  ALL_TASKS_COUNT=${#ALL_TASKS_FILES[@]}
  if [[ $SELECTED_IDX -ge $ALL_TASKS_COUNT ]]; then
    SELECTED_IDX=$((ALL_TASKS_COUNT > 0 ? ALL_TASKS_COUNT - 1 : 0))
  fi
}

# ── Tab bar ───────────────────────────────────────────────────────────
# Must be called in the parent shell (NOT inside $(...)) to update globals
update_worker_state() {
  if ! cache_expired; then
    # Use cached worker list — skip expensive detection
    MAX_TABS=$((2 + ACTIVE_WORKER_COUNT))
    return
  fi
  DETECTED_WORKERS=()
  local raw; raw=$(detect_workers)
  [[ -n "$raw" ]] && read -ra DETECTED_WORKERS <<< "$raw"
  ACTIVE_WORKER_COUNT=${#DETECTED_WORKERS[@]}
  MAX_TABS=$((2 + ACTIVE_WORKER_COUNT))
  # Rebuild worker task hints (expensive — only on cache refresh)
  CACHED_WORKER_HINTS=()
  local w
  for ((w=0; w<ACTIVE_WORKER_COUNT; w++)); do
    CACHED_WORKER_HINTS+=("$(get_worker_active_task "${DETECTED_WORKERS[$w]}")")
  done
}

render_tabs_str() {
  local out="  "
  if [[ $CURRENT_TAB -eq 1 ]]; then
    out+="${REV}${BOLD} 1:Overview ${RESET}"
  else
    out+="${DIM} 1:Overview ${RESET}"
  fi
  if [[ $CURRENT_TAB -eq 2 ]]; then
    out+="${REV}${BOLD} 2:Activity ${RESET}"
  else
    out+="${DIM} 2:Activity ${RESET}"
  fi
  local idx=3 i=0
  for ((i=0; i<ACTIVE_WORKER_COUNT; i++)); do
    local w="${DETECTED_WORKERS[$i]}"
    local task_hint="${CACHED_WORKER_HINTS[$i]:-}"
    local label="${idx}:${w}"
    [[ -n "$task_hint" ]] && label="${idx}:${w} ${task_hint}"
    if [[ $CURRENT_TAB -eq $idx ]]; then
      out+="${REV}${BOLD} ${label} ${RESET}"
    else
      out+="${DIM} ${label} ${RESET}"
    fi
    idx=$((idx + 1))
  done
  printf '%s' "$out"
}

# ── Colorized log renderer ───────────────────────────────────────────
render_log_lines() {
  local log_file="$1" lines="$2"
  while IFS= read -r line; do
    if [[ "$line" == *"error"* || "$line" == *"Error"* || "$line" == *"FAIL"* || "$line" == *"auto-rejecting"* ]]; then
      buf_line "  ${RED}$line${RESET}"
    elif [[ "$line" == *"✓"* || "$line" == *"PASS"* || "$line" == *"success"* ]]; then
      buf_line "  ${GREEN}$line${RESET}"
    elif [[ "$line" == *"──"* || "$line" == *"═══"* ]]; then
      buf_line "  ${CYAN}$line${RESET}"
    else
      buf_line "  $line"
    fi
  done < <(tail -n "$lines" "$log_file" 2>/dev/null)
}

# ── Log file cache (built once per cache cycle) ─────────────────────
build_log_cache() {
  cache_expired || return 0
  CACHED_LOG_MAP=()
  local f fname
  # Scan main log dir
  if [[ -d "$LOG_DIR" ]]; then
    for f in "$LOG_DIR"/*.log; do
      [[ -f "$f" ]] || continue
      fname="${f##*/}"; fname="${fname%.log}"
      CACHED_LOG_MAP+=("${fname}|${f}")
    done
  fi
  # Scan worktree log dirs
  local wt_log
  for wt_log in "$WORKTREE_BASE"/worker-*/.opencode/pipeline/logs; do
    [[ -d "$wt_log" ]] || continue
    for f in "$wt_log"/*.log; do
      [[ -f "$f" ]] || continue
      fname="${f##*/}"; fname="${fname%.log}"
      CACHED_LOG_MAP+=("${fname}|${f}")
    done
  done
}

# ── Tab: Overview ─────────────────────────────────────────────────────
render_overview() {
  get_terminal_size
  buf_reset
  update_worker_state
  count_all_task_dirs
  build_log_cache
  build_task_list
  aggregate_batch_tokens
  query_openrouter_balance
  build_provider_line

  # Use cached counts (refreshed by count_all_task_dirs in render cycle)
  local todo_count=$CACHED_TODO_COUNT
  local in_progress_count=$CACHED_INPROG_COUNT
  local done_count=$CACHED_DONE_COUNT
  local failed_count=$CACHED_FAILED_COUNT
  local total=$((todo_count + in_progress_count + done_count + failed_count))
  local completed=$((done_count + failed_count))

  buf_line "${CYAN}${BOLD}  Pipeline Monitor${RESET} ${DIM}v${MONITOR_VERSION}${RESET}  $(date '+%H:%M:%S')"
  buf_line "${DIM}$(hline)${RESET}"
  buf_line "$(render_tabs_str)"
  buf_line ""

  if [[ "$LOG_VIEW_MODE" == true ]]; then
    render_task_log_view_buf
    buf_flush
    return
  fi

  if [[ "$DETAIL_MODE" == true && -n "$DETAIL_FILE" && -f "$DETAIL_FILE" ]]; then
    render_task_detail_buf
    buf_flush
    return
  fi

  buf_line "  $(progress_bar_str "$completed" "$total")"
  buf_line ""
  buf_line "$(printf "  ${BLUE}${BOLD}⏳ Todo:${RESET}        %-4d  ${YELLOW}${BOLD}🔄 In Progress:${RESET} %-4d  ${GREEN}${BOLD}✓ Done:${RESET}        %-4d  ${RED}${BOLD}✗ Failed:${RESET}      %-4d" "$todo_count" "$in_progress_count" "$done_count" "$failed_count")"
  buf_line ""

  # Status line with worker count
  local batch_pid; batch_pid=$(pgrep -f 'pipeline-batch.sh' 2>/dev/null | head -1 || true)
  if [[ -n "$batch_pid" ]]; then
    local worker_label=""
    if [[ $ACTIVE_WORKER_COUNT -gt 0 ]]; then
      worker_label=", ${ACTIVE_WORKER_COUNT} workers"
    else
      worker_label=", 1 worker"
    fi
    local batch_start
    batch_start=$(ps -o lstart= -p "$batch_pid" 2>/dev/null | xargs -I{} date -jf '%c' '{}' '+%s' 2>/dev/null || echo "")
    if [[ -n "$batch_start" ]]; then
      local elapsed=$(( $(date +%s) - batch_start ))
      buf_line "  ${BOLD}Status:${RESET} ${GREEN}Running${RESET}  ($(format_duration "$elapsed") elapsed, PID ${batch_pid}${worker_label})"
    else
      buf_line "  ${BOLD}Status:${RESET} ${GREEN}Running${RESET}  (PID ${batch_pid}${worker_label})"
    fi
  else
    if [[ $todo_count -gt 0 ]]; then
      if [[ "$AUTOSTART" == "true" ]]; then
        buf_line "  ${BOLD}Status:${RESET} ${CYAN}Auto-start${RESET} — ${BOLD}${todo_count} tasks waiting${RESET}, launching workers…"
      else
        buf_line "  ${BOLD}Status:${RESET} ${YELLOW}Not running${RESET} — ${BOLD}${todo_count} tasks waiting${RESET}, press ${WHITE}[s]${RESET} to start"
      fi
    else
      buf_line "  ${BOLD}Status:${RESET} ${DIM}Not running${RESET}"
    fi
  fi
  buf_line ""
  buf_line "${DIM}$(hline)${RESET}"

  # Task list with cursor
  local available_lines=$((TERM_ROWS - 18))
  [[ $available_lines -lt 5 ]] && available_lines=5
  local scroll_start=0
  [[ $SELECTED_IDX -ge $available_lines ]] && scroll_start=$((SELECTED_IDX - available_lines + 1))

  local prev_state=""
  for ((i=0; i<ALL_TASKS_COUNT; i++)); do
    [[ $i -lt $scroll_start ]] && continue
    if [[ $((i - scroll_start)) -ge $available_lines ]]; then
      buf_line "  ${DIM}  ... $((ALL_TASKS_COUNT - i)) more${RESET}"
      break
    fi
    local state="${ALL_TASKS_STATES[$i]}" title="${ALL_TASKS_TITLES[$i]}" file="${ALL_TASKS_FILES[$i]}"
    local state_base="${state%%:*}"
    if [[ "$state_base" != "$prev_state" ]]; then
      case "$state_base" in
        in-progress) buf_line "  ${YELLOW}${BOLD}In Progress:${RESET}" ;;
        done)        buf_line "  ${GREEN}${BOLD}Completed:${RESET}" ;;
        failed)      buf_line "  ${RED}${BOLD}Failed:${RESET}" ;;
        todo)        buf_line "  ${BLUE}${BOLD}Waiting:${RESET} ${DIM}(priority order)${RESET}" ;;
      esac
      prev_state="$state_base"
    fi
    local cursor="  "
    [[ $i -eq $SELECTED_IDX ]] && cursor="${CYAN}▶${RESET} "
    case "$state_base" in
      in-progress)
        local stage="..."
        local fname="${file##*/}"; fname="${fname%.md}"
        # Use cached log map instead of ls -t glob per task
        local log_entry
        for log_entry in ${CACHED_LOG_MAP[@]+"${CACHED_LOG_MAP[@]}"}; do
          if [[ "$log_entry" == "${fname}|"* ]]; then
            local lpath="${log_entry#*|}"; local lbase="${lpath##*/}"; lbase="${lbase%.log}"
            # Strip leading timestamp digits+underscores
            stage="${lbase##+([0-9_])}"
            break
          fi
        done
        buf_line "  ${cursor}  ${YELLOW}▸${RESET} $title ${DIM}[$stage]${RESET}"
        ;;
      done)
        # Read batch metadata line with bash read (no grep+sed subshells)
        local batch_line="" duration="?" branch="?"
        IFS= read -r batch_line < "$file" 2>/dev/null || true
        if [[ "$batch_line" == *"<!-- batch:"* ]]; then
          [[ "$batch_line" =~ duration:\ ([0-9]+)s ]] && duration="${BASH_REMATCH[1]}"
          [[ "$batch_line" =~ branch:\ ([^\ ]+) ]] && branch="${BASH_REMATCH[1]%% -->}"
        fi
        if [[ "$duration" =~ ^[0-9]+$ ]]; then
          buf_line "  ${cursor}  ${GREEN}✓${RESET} $title ${DIM}($(format_duration "$duration"), $branch)${RESET}"
        else
          buf_line "  ${cursor}  ${GREEN}✓${RESET} $title"
        fi ;;
      failed)
        local batch_line="" duration="?"
        IFS= read -r batch_line < "$file" 2>/dev/null || true
        [[ "$batch_line" =~ duration:\ ([0-9]+)s ]] && duration="${BASH_REMATCH[1]}"
        if [[ "$duration" =~ ^[0-9]+$ ]]; then
          buf_line "  ${cursor}  ${RED}✗${RESET} $title ${DIM}($(format_duration "$duration"))${RESET}"
        else
          buf_line "  ${cursor}  ${RED}✗${RESET} $title"
        fi ;;
      todo)
        local prio="${state#todo:}" prio_label=""
        [[ "$prio" -gt 1 ]] && prio_label=" ${MAGENTA}#${prio}${RESET}"
        buf_line "  ${cursor}  ${DIM}○${RESET} ${title}${prio_label}"
        ;;
    esac
  done

  buf_line ""
  buf_line "${DIM}$(hline)${RESET}"
  render_cost_bar
  [[ -n "$ACTION_MSG" ]] && { buf_line "  $ACTION_MSG"; ACTION_MSG=""; }
  render_bottom_menu_buf
  buf_flush
}

# ── Context-aware bottom menu ─────────────────────────────────────────
render_bottom_menu_buf() {
  local state=""
  [[ $ALL_TASKS_COUNT -gt 0 && $SELECTED_IDX -lt $ALL_TASKS_COUNT ]] && state="${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}"
  local batch_running=false; is_batch_running && batch_running=true
  local keys="  ${DIM}←/→ tabs  ↑/↓ select  Enter detail"
  case "$state" in
    in-progress) keys="$keys  ${WHITE}[x]${DIM} stop  ${WHITE}[l]${DIM} logs" ;;
    failed) keys="$keys  ${WHITE}[f]${DIM} retry  ${WHITE}[d]${DIM} delete  ${WHITE}[l]${DIM} logs" ;;
    todo)
      keys="$keys  ${WHITE}[+]${DIM} prio+  ${WHITE}[-]${DIM} prio-  ${WHITE}[d]${DIM} delete"
      $batch_running && keys="$keys  ${WHITE}[s]${DIM} start extra"
      ;;
    done) keys="$keys  ${WHITE}[a]${DIM} archive" ;;
  esac
  ! $batch_running && keys="$keys  ${WHITE}[s]${DIM} start"
  keys="$keys  ${WHITE}[a]${DIM} archive  ${WHITE}[k]${DIM} kill  ${WHITE}[q]${DIM} quit${RESET}"
  buf_line "$keys"
}

# ── Task detail view ──────────────────────────────────────────────────
render_task_detail_buf() {
  buf_line ""
  buf_line "  ${BOLD}Task Detail${RESET}  ${DIM}(Esc to go back)${RESET}"
  buf_line ""
  local title; title=$(grep -m1 '^# ' "$DETAIL_FILE" 2>/dev/null | sed 's/^# //' || basename "$DETAIL_FILE" .md)
  buf_line "  ${BOLD}$title${RESET}"
  buf_line ""
  local available_lines=$((TERM_ROWS - 14))
  [[ $available_lines -lt 5 ]] && available_lines=5
  while IFS= read -r line; do
    buf_line "  $line"
  done < <(grep -v '^<!-- priority:' "$DETAIL_FILE" 2>/dev/null | grep -v '^<!-- batch:' | head -n "$available_lines")
  buf_line ""
  buf_line "${DIM}$(hline)${RESET}"
  buf_line "  ${DIM}Esc back  [q] quit${RESET}"
}

# ── Task log view ────────────────────────────────────────────────────
render_task_log_view_buf() {
  if [[ -z "$LOG_VIEW_FILE" || ! -f "$LOG_VIEW_FILE" ]]; then
    buf_line "  ${DIM}No log file found for this task${RESET}"
    buf_line "  ${DIM}q/Esc back${RESET}"
  else
    local log_name="${LOG_VIEW_FILE##*/}"
    local log_size
    log_size=$(stat -f%z "$LOG_VIEW_FILE" 2>/dev/null || wc -c < "$LOG_VIEW_FILE" | tr -d ' ')
    buf_line "  ${BOLD}Task Logs${RESET}  ${DIM}${log_name}  $(( log_size / 1024 ))KB  (q/Esc back)${RESET}"
    local available_lines=$((TERM_ROWS - 7))
    [[ $available_lines -lt 5 ]] && available_lines=5
    render_log_lines "$LOG_VIEW_FILE" "$available_lines"
  fi
}

find_task_log() {
  local file="$1"
  local fname="${file##*/}"; fname="${fname%.md}"
  # Use cached log map first
  local entry
  for entry in ${CACHED_LOG_MAP[@]+"${CACHED_LOG_MAP[@]}"}; do
    if [[ "$entry" == *"${fname}"* ]]; then
      printf '%s' "${entry#*|}"
      return
    fi
  done
  # Fallback: direct glob (rare — cache should cover it)
  local log=""
  log=$(ls -t "$LOG_DIR"/*"${fname}"* 2>/dev/null | head -1 || true)
  echo "$log"
}

# ── Tab: Activity Log (always present) ────────────────────────────────
format_duration() {
  local secs="$1"
  if [[ $secs -ge 3600 ]]; then
    printf '%dh %dm' $((secs / 3600)) $(((secs % 3600) / 60))
  elif [[ $secs -ge 60 ]]; then
    printf '%dm %ds' $((secs / 60)) $((secs % 60))
  else
    printf '%ds' "$secs"
  fi
}

format_epoch() {
  if date -d "@0" '+%H:%M:%S' &>/dev/null 2>&1; then
    date -d "@$1" '+%H:%M:%S' 2>/dev/null || echo "??:??:??"
  elif date -r 0 '+%H:%M:%S' &>/dev/null 2>&1; then
    date -r "$1" '+%H:%M:%S' 2>/dev/null || echo "??:??:??"
  else
    echo "??:??:??"
  fi
}

format_tokens() {
  local n="$1"
  if [[ $n -ge 1000000 ]]; then
    printf '%.1fM' "$(echo "$n" | awk '{printf "%.1f", $1/1000000}')"
  elif [[ $n -ge 1000 ]]; then
    printf '%.1fk' "$(echo "$n" | awk '{printf "%.1f", $1/1000}')"
  else
    printf '%d' "$n"
  fi
}

format_cost() {
  local cost="$1"
  if [[ "$cost" == "0" || -z "$cost" ]]; then
    echo "\$0"
  else
    echo "\$$(echo "$cost" | awk '{printf "%.2f", $1}')"
  fi
}

# Detect which agent is currently running by checking opencode processes
detect_live_agent() {
  local worker_dir="$1"
  local wt_log="$worker_dir/.opencode/pipeline/logs"
  [[ -d "$wt_log" ]] || return

  # Find the newest .log file without a matching .meta.json (= still running)
  local log_file
  for log_file in $(ls -t "$wt_log"/*.log 2>/dev/null); do
    local base="${log_file%.log}"
    if [[ ! -f "${base}.meta.json" ]]; then
      # Extract agent name from filename: TIMESTAMP_agent.log
      local fname; fname=$(basename "$log_file" .log)
      local agent_name="${fname##*_}"
      local log_size
      log_size=$(wc -c < "$log_file" 2>/dev/null | tr -d ' ')
      # Extract start time from filename (YYYYMMDD_HHMMSS_agent.log) — immutable
      local ts_part="${fname%_*}"  # "20260319_064919"
      local started_epoch=0
      if [[ "$ts_part" =~ ^([0-9]{4})([0-9]{2})([0-9]{2})_([0-9]{2})([0-9]{2})([0-9]{2})$ ]]; then
        local dt="${BASH_REMATCH[1]}-${BASH_REMATCH[2]}-${BASH_REMATCH[3]} ${BASH_REMATCH[4]}:${BASH_REMATCH[5]}:${BASH_REMATCH[6]}"
        started_epoch=$(date -d "$dt" +%s 2>/dev/null || date -jf '%Y-%m-%d %H:%M:%S' "$dt" +%s 2>/dev/null || echo "0")
      fi
      # Fallback to file birth/creation time if filename parse failed
      [[ "$started_epoch" -eq 0 ]] && started_epoch=$(stat -c '%W' "$log_file" 2>/dev/null || stat -c '%Y' "$log_file" 2>/dev/null || echo "0")
      echo "${agent_name}|${started_epoch}|${log_size}"
      return
    fi
  done
}

# Build activity data: completed steps + live step per worker
# Only shows meta from worktree log dirs (current/recent batch), not old main logs
build_activity_data() {
  ACTIVITY_STEPS=()       # "worker|agent|model|status|started|finished|duration|in_tok|out_tok|cache_r|cost"
  ACTIVITY_LIVE=()        # "worker|agent|started|log_bytes" (currently running)
  ACTIVITY_TASK_TITLE=""
  ACTIVITY_TASK_STARTED=0
  ACTIVITY_TOTAL_COST="0"
  ACTIVITY_TOTAL_IN=0
  ACTIVITY_TOTAL_OUT=0

  # Get current task title from in-progress
  local ip_file
  for ip_file in "$TASK_SOURCE/in-progress"/*.md; do
    [[ -f "$ip_file" ]] || continue
    # Extract title from first # heading
    ACTIVITY_TASK_TITLE=$(grep -m1 '^# ' "$ip_file" 2>/dev/null | sed 's/^# //')
    break
  done

  # Scan each worktree for meta.json files
  local worker_dir worker_name
  for worker_dir in "$WORKTREE_BASE"/worker-*; do
    [[ -d "$worker_dir" ]] || continue
    worker_name=$(basename "$worker_dir")
    local wt_log="$worker_dir/.opencode/pipeline/logs"
    [[ -d "$wt_log" ]] || continue

    # Parse completed agent steps (meta.json = agent finished)
    local meta_file
    for meta_file in $(ls -t "$wt_log"/*.meta.json 2>/dev/null); do
      local parsed
      parsed=$(awk '
        /"agent"/           { gsub(/.*"agent"[[:space:]]*:[[:space:]]*"/, ""); gsub(/".*/, ""); a=$0 }
        /"model"/           { gsub(/.*"model"[[:space:]]*:[[:space:]]*"/, ""); gsub(/".*/, ""); m=$0 }
        /"started_epoch"/   { gsub(/.*"started_epoch"[[:space:]]*:[[:space:]]*/, ""); gsub(/[^0-9].*/, ""); s=$0 }
        /"finished_epoch"/  { gsub(/.*"finished_epoch"[[:space:]]*:[[:space:]]*/, ""); gsub(/[^0-9].*/, ""); f=$0 }
        /"duration_seconds"/{ gsub(/.*"duration_seconds"[[:space:]]*:[[:space:]]*/, ""); gsub(/[^0-9].*/, ""); d=$0 }
        /"exit_code"/       { gsub(/.*"exit_code"[[:space:]]*:[[:space:]]*/, ""); gsub(/[^0-9].*/, ""); e=$0 }
        /"input_tokens"/    { gsub(/.*"input_tokens"[[:space:]]*:[[:space:]]*/, ""); gsub(/[^0-9].*/, ""); it=$0 }
        /"output_tokens"/   { gsub(/.*"output_tokens"[[:space:]]*:[[:space:]]*/, ""); gsub(/[^0-9].*/, ""); ot=$0 }
        /"cache_read"/      { gsub(/.*"cache_read"[[:space:]]*:[[:space:]]*/, ""); gsub(/[^0-9].*/, ""); cr=$0 }
        /"cost"/            { gsub(/.*"cost"[[:space:]]*:[[:space:]]*/, ""); gsub(/[^0-9.].*/, ""); c=$0 }
        END { printf "%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s", a, m, s, f, d, e, it, ot, cr, c }
      ' "$meta_file" 2>/dev/null) || continue
      local agent model started finished duration exit_code in_tok out_tok cache_r cost
      IFS=$'\t' read -r agent model started finished duration exit_code in_tok out_tok cache_r cost <<< "$parsed"
      [[ -z "$agent" || -z "$started" ]] && continue

      local status="ok"
      [[ "${exit_code:-0}" -ne 0 ]] && status="fail"

      # Track earliest start as task start
      [[ "$started" -gt 0 && ( "$ACTIVITY_TASK_STARTED" -eq 0 || "$started" -lt "$ACTIVITY_TASK_STARTED" ) ]] && ACTIVITY_TASK_STARTED="$started"

      ACTIVITY_STEPS+=("${worker_name}|${agent}|${model}|${status}|${started}|${finished}|${duration:-0}|${in_tok:-0}|${out_tok:-0}|${cache_r:-0}|${cost:-0}")

      # Accumulate totals
      ACTIVITY_TOTAL_IN=$(( ACTIVITY_TOTAL_IN + ${in_tok:-0} ))
      ACTIVITY_TOTAL_OUT=$(( ACTIVITY_TOTAL_OUT + ${out_tok:-0} ))
      ACTIVITY_TOTAL_COST=$(echo "$ACTIVITY_TOTAL_COST ${cost:-0}" | awk '{printf "%.4f", $1 + $2}')
    done

    # Detect live (currently running) agent
    local live_info
    live_info=$(detect_live_agent "$worker_dir")
    if [[ -n "$live_info" ]]; then
      ACTIVITY_LIVE+=("${worker_name}|${live_info}")
    fi
  done

  # Sort completed steps by started epoch ascending (oldest first = pipeline order)
  if [[ ${#ACTIVITY_STEPS[@]} -gt 0 ]]; then
    IFS=$'\n' ACTIVITY_STEPS=($(printf '%s\n' "${ACTIVITY_STEPS[@]}" | sort -t'|' -k5 -n)); unset IFS
  fi
}

render_logs_tab() {
  get_terminal_size
  buf_reset
  update_worker_state
  build_log_cache
  aggregate_batch_tokens
  query_openrouter_balance
  build_provider_line
  buf_line "$(render_tabs_str)  ${DIM}v${MONITOR_VERSION}  $(date '+%H:%M:%S')${RESET}"

  build_activity_data

  # ── Header: current task ──
  if [[ -n "$ACTIVITY_TASK_TITLE" ]]; then
    buf_line "  ${BOLD}${WHITE}${ACTIVITY_TASK_TITLE}${RESET}"
  else
    buf_line "  ${BOLD}Pipeline Activity${RESET}"
  fi

  local now; now=$(date +%s)

  # ── Elapsed time ──
  if [[ "$ACTIVITY_TASK_STARTED" -gt 0 ]]; then
    local elapsed=$(( now - ACTIVITY_TASK_STARTED ))
    buf_line "  ${DIM}Elapsed: $(format_duration "$elapsed")  |  Tokens: $(format_tokens "$ACTIVITY_TOTAL_IN") in / $(format_tokens "$ACTIVITY_TOTAL_OUT") out  |  Cost: $(format_cost "$ACTIVITY_TOTAL_COST")${RESET}"
  fi
  buf_line ""

  local available_lines=$((TERM_ROWS - 8))
  [[ $available_lines -lt 5 ]] && available_lines=5
  local shown=0

  # ── Read events.log (live feed from pipeline.sh) ──
  local events_shown=false
  local event_file=""
  for wt in "$WORKTREE_BASE"/worker-*; do
    [[ -d "$wt" ]] || continue
    local ef="$wt/.opencode/pipeline/events.log"
    [[ -f "$ef" ]] && event_file="$ef"
  done

  if [[ -n "$event_file" ]]; then
    # Show last N events from event log (newest at bottom for timeline feel)
    local max_events=$((available_lines - ${#ACTIVITY_LIVE[@]} - 2))
    [[ $max_events -lt 3 ]] && max_events=3
    local event_lines
    event_lines=$(tail -"$max_events" "$event_file" 2>/dev/null || true)

    if [[ -n "$event_lines" ]]; then
      events_shown=true
      while IFS= read -r eline; do
        [[ $shown -ge $available_lines ]] && break
        IFS='|' read -r e_epoch e_time e_type e_details <<< "$eline"

        case "$e_type" in
          TASK_START)
            local e_task; e_task=$(echo "$e_details" | sed 's/task=//;s/|.*//')
            local e_worker; e_worker=$(echo "$e_details" | grep -oP 'worker=\K[^|]+' || echo "")
            buf_line "  ${BLUE}▶${RESET} ${DIM}${e_time}${RESET}  ${BOLD}${WHITE}${e_task}${RESET}  ${DIM}#${e_worker}${RESET}"
            ;;
          PLAN)
            local e_profile; e_profile=$(echo "$e_details" | grep -oP 'profile=\K[^|]+' || echo "?")
            local e_agents; e_agents=$(echo "$e_details" | grep -oP 'agents=\K.*' || echo "?")
            buf_line "  ${MAGENTA}◆${RESET} ${DIM}${e_time}${RESET}  ${MAGENTA}plan${RESET}  ${WHITE}${e_profile}${RESET}  ${DIM}${e_agents}${RESET}"
            ;;
          AGENT_START)
            local e_agent; e_agent=$(echo "$e_details" | grep -oP 'agent=\K[^|]+' || echo "?")
            local e_model; e_model=$(echo "$e_details" | grep -oP 'model=\K.*' || echo "?")
            e_model="${e_model#*/}"
            e_model="${e_model/claude-opus-4-20250514/opus-4}"
            e_model="${e_model/claude-sonnet-4-20250514/sonnet-4}"
            e_model="${e_model/codex-mini-latest/codex-mini}"
            buf_line "  ${YELLOW}▸${RESET} ${DIM}${e_time}${RESET}  ${YELLOW}${e_agent}${RESET}  ${DIM}${e_model}${RESET}"
            ;;
          AGENT_DONE)
            local e_agent; e_agent=$(echo "$e_details" | grep -oP 'agent=\K[^|]+' || echo "?")
            local e_status; e_status=$(echo "$e_details" | grep -oP 'status=\K[^|]+' || echo "?")
            local e_dur; e_dur=$(echo "$e_details" | grep -oP 'duration=\K[^|]+' || echo "")
            local e_tok; e_tok=$(echo "$e_details" | grep -oP 'tokens=\K[^|]+' || echo "")
            local icon="${GREEN}✓${RESET}"
            [[ "$e_status" != "ok" ]] && icon="${RED}✗${RESET}"
            local extra=""
            [[ -n "$e_dur" ]] && extra+="  ${WHITE}${e_dur}${RESET}"
            [[ -n "$e_tok" ]] && extra+="  ${DIM}${e_tok}${RESET}"
            buf_line "  ${icon} ${DIM}${e_time}${RESET}  ${CYAN}${e_agent}${RESET}${extra}"
            ;;
          *)
            buf_line "  ${DIM}${e_time}  ${e_type}  ${e_details}${RESET}"
            ;;
        esac
        ((shown++))
      done <<< "$event_lines"
    fi
  fi

  if [[ "$events_shown" != true ]]; then
    # Fallback to meta.json for completed steps
    if [[ ${#ACTIVITY_STEPS[@]} -eq 0 && ${#ACTIVITY_LIVE[@]} -eq 0 ]]; then
      buf_line "  ${DIM}No activity yet. Waiting for pipeline to start...${RESET}"
      buf_line "  ${DIM}Press [s] to start manually${RESET}"
    else
      local step
      for step in "${ACTIVITY_STEPS[@]}"; do
        [[ $shown -ge $available_lines ]] && break
        IFS='|' read -r s_worker s_agent s_model s_status s_started s_finished s_duration s_in s_out s_cache s_cost <<< "$step"
        local time_str; time_str=$(format_epoch "$s_started")
        local dur_str; dur_str=$(format_duration "$s_duration")
        local status_icon="${GREEN}✓${RESET}"
        [[ "$s_status" != "ok" ]] && status_icon="${RED}✗${RESET}"
        local model_short="${s_model#*/}"
        model_short="${model_short/claude-opus-4-20250514/opus-4}"
        model_short="${model_short/claude-sonnet-4-20250514/sonnet-4}"
        model_short="${model_short/codex-mini-latest/codex-mini}"
        local tok_str=""
        local total_tok=$(( s_in + s_out ))
        [[ $total_tok -gt 0 ]] && tok_str="$(format_tokens "$s_in")→$(format_tokens "$s_out")"
        buf_line "  ${status_icon} ${DIM}${time_str}${RESET}  ${CYAN}${BOLD}${s_agent}${RESET}  ${DIM}${model_short}${RESET}  ${WHITE}${dur_str}${RESET}  ${DIM}${tok_str}${RESET}"
        ((shown++))
      done
    fi
  fi

  # ── Live (currently running) steps ──
  if [[ ${#ACTIVITY_LIVE[@]} -gt 0 ]]; then
    local live
    for live in "${ACTIVITY_LIVE[@]}"; do
      [[ $shown -ge $available_lines ]] && break
      IFS='|' read -r l_worker l_agent l_started l_bytes <<< "$live"

      local elapsed=$(( now - l_started ))
      local elapsed_str; elapsed_str=$(format_duration "$elapsed")
      local bytes_kb=$(( l_bytes / 1024 ))

      # Animated spinner
      local spin_chars='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
      local spin_idx=$(( (now % 10) ))
      local spinner="${spin_chars:$spin_idx:1}"

      buf_line "  ${YELLOW}${spinner}${RESET} ${DIM}$(format_epoch "$l_started")${RESET}  ${YELLOW}${BOLD}${l_agent}${RESET}  ${YELLOW}running ${elapsed_str}${RESET}  ${DIM}${bytes_kb}KB log${RESET}  ${DIM}#${l_worker}${RESET}"
      ((shown++))
    done
  fi

  # Fill remaining lines
  while [[ $shown -lt $((available_lines - 2)) ]]; do
    buf_line ""
    ((shown++))
  done

  render_cost_bar
  buf_line "  ${DIM}←/→ tabs  [s] start  [k] kill  [q] quit  (auto-refresh ${REFRESH_INTERVAL}s)${RESET}"
  buf_flush
}

# ── Tab: Worker log ───────────────────────────────────────────────────
render_worker_tab() {
  local worker_name="worker-${1}"
  get_terminal_size
  buf_reset
  update_worker_state
  build_log_cache
  aggregate_batch_tokens
  query_openrouter_balance
  build_provider_line
  buf_line "$(render_tabs_str)  ${DIM}v${MONITOR_VERSION}  $(date '+%H:%M:%S')${RESET}"

  # Find latest log from cache (avoids ls -t glob)
  local log_file="" entry
  for entry in ${CACHED_LOG_MAP[@]+"${CACHED_LOG_MAP[@]}"}; do
    local lpath="${entry#*|}"
    if [[ "$lpath" == *"$worker_name"* ]]; then
      log_file="$lpath"
      break
    fi
  done
  # Fallback if cache miss
  if [[ -z "$log_file" ]]; then
    local wt_log_dir="$WORKTREE_BASE/$worker_name/.opencode/pipeline/logs"
    [[ -d "$wt_log_dir" ]] && log_file=$(ls -t "$wt_log_dir"/*.log 2>/dev/null | head -1 || true)
    [[ -z "$log_file" && -d "$LOG_DIR" ]] && log_file=$(ls -t "$LOG_DIR"/*.log 2>/dev/null | head -1 || true)
  fi

  if [[ -z "$log_file" ]]; then
    buf_line "  ${DIM}No active log found for $worker_name${RESET}"
    buf_line "  ${DIM}Log directory: $WORKTREE_BASE/$worker_name/.opencode/pipeline/logs${RESET}"
  else
    local log_base="${log_file##*/}"; local agent_name="${log_base%.log}"
    agent_name="${agent_name##+([0-9_])}"
    local log_size
    log_size=$(stat -f%z "$log_file" 2>/dev/null || wc -c < "$log_file" | tr -d ' ')
    buf_line "  ${YELLOW}$agent_name${RESET}  ${DIM}${log_base}  $(( log_size / 1024 ))KB${RESET}"
    local available_lines=$((TERM_ROWS - 4))
    [[ $available_lines -lt 5 ]] && available_lines=5
    render_log_lines "$log_file" "$available_lines"
  fi

  render_cost_bar
  buf_line "  ${DIM}←/→ tabs  [s] start  [k] kill  [q] quit  (auto-refresh ${REFRESH_INTERVAL}s)${RESET}"
  buf_flush
}

# ── Actions ───────────────────────────────────────────────────────────
action_start() {
  if is_batch_running; then
    action_start_task; return
  fi
  local todo_count; todo_count=$(count_files "$TASK_SOURCE/todo")
  if [[ $todo_count -eq 0 ]]; then
    ACTION_MSG="${YELLOW}No tasks in todo/${RESET}"; return
  fi
  local _caff=""; command -v caffeinate &>/dev/null && _caff="caffeinate -s"
  $_caff nohup "$REPO_ROOT/builder/pipeline-batch.sh" \
    --workers "$WORKERS" --no-stop-on-failure --watch "$TASK_SOURCE" \
    > "$REPO_ROOT/batch.log" 2>&1 &
  ACTION_MSG="${GREEN}Started batch ($todo_count tasks, $WORKERS workers, PID $!)${RESET}"
}

action_retry_failed() {
  if is_batch_running; then ACTION_MSG="${RED}Batch running — kill first (k)${RESET}"; return; fi
  local fail_dir="$TASK_SOURCE/failed" todo_dir="$TASK_SOURCE/todo" count=0
  [[ -d "$fail_dir" ]] || { ACTION_MSG="${YELLOW}No failed/ directory${RESET}"; return; }
  mkdir -p "$todo_dir"
  for f in "$fail_dir"/*.md; do
    [[ -f "$f" ]] || continue
    local branch_name; branch_name=$(grep -m1 '<!-- batch:' "$f" 2>/dev/null | sed 's/.*branch: \([^ ]*\) -->.*/\1/' || true)
    [[ -n "$branch_name" && "$branch_name" != "$(cat "$f")" ]] && git -C "$REPO_ROOT" branch -D "$branch_name" 2>/dev/null || true
    sed '/^<!-- batch:.*-->$/d' "$f" > "$todo_dir/$(basename "$f")"
    rm -f "$f"; count=$((count + 1))
  done
  [[ $count -eq 0 ]] && { ACTION_MSG="${YELLOW}No failed tasks to retry${RESET}"; return; }
  action_start
  ACTION_MSG="${GREEN}Moved $count failed→todo and started batch${RESET}"
}

action_kill() {
  local pids; pids=$(pgrep -f 'pipeline-batch.sh' 2>/dev/null || true)
  [[ -z "$pids" ]] && { ACTION_MSG="${YELLOW}No batch running${RESET}"; return; }
  pkill -f 'pipeline-batch.sh' 2>/dev/null || true
  pkill -f 'opencode run --agent' 2>/dev/null || true
  ACTION_MSG="${RED}Killed batch processes${RESET}"
}

action_stop_task() {
  [[ $ALL_TASKS_COUNT -eq 0 || $SELECTED_IDX -ge $ALL_TASKS_COUNT ]] && return
  local state="${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}"
  [[ "$state" != "in-progress" ]] && { ACTION_MSG="${YELLOW}Can only stop in-progress tasks${RESET}"; return; }
  local file="${ALL_TASKS_FILES[$SELECTED_IDX]}" title="${ALL_TASKS_TITLES[$SELECTED_IDX]}"
  mkdir -p "$TASK_SOURCE/todo"
  sed '/^<!-- batch:.*-->$/d' "$file" > "$TASK_SOURCE/todo/$(basename "$file")"
  rm -f "$file"
  ACTION_MSG="${YELLOW}Stopped: ${title} → moved to todo${RESET}"
}

action_promote() {
  [[ $ALL_TASKS_COUNT -eq 0 || $SELECTED_IDX -ge $ALL_TASKS_COUNT ]] && return
  [[ "${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}" != "todo" ]] && { ACTION_MSG="${YELLOW}Can only change priority of todo tasks${RESET}"; return; }
  local file="${ALL_TASKS_FILES[$SELECTED_IDX]}"
  local prio; prio=$(get_priority "$file")
  set_priority "$file" "$((prio + 1))"
  ACTION_MSG="${MAGENTA}Priority → #$((prio + 1))${RESET}"
}

action_demote() {
  [[ $ALL_TASKS_COUNT -eq 0 || $SELECTED_IDX -ge $ALL_TASKS_COUNT ]] && return
  [[ "${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}" != "todo" ]] && { ACTION_MSG="${YELLOW}Can only change priority of todo tasks${RESET}"; return; }
  local file="${ALL_TASKS_FILES[$SELECTED_IDX]}"
  local prio; prio=$(get_priority "$file")
  local new=$((prio > 1 ? prio - 1 : 1))
  set_priority "$file" "$new"
  ACTION_MSG="${MAGENTA}Priority → #${new}${RESET}"
}

action_delete() {
  [[ $ALL_TASKS_COUNT -eq 0 || $SELECTED_IDX -ge $ALL_TASKS_COUNT ]] && return
  local state="${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}"
  [[ "$state" != "todo" && "$state" != "failed" ]] && { ACTION_MSG="${YELLOW}Can only delete waiting or failed tasks${RESET}"; return; }
  local file="${ALL_TASKS_FILES[$SELECTED_IDX]}" title="${ALL_TASKS_TITLES[$SELECTED_IDX]}"
  local fname; fname=$(basename "$file" .md)
  local branch_name; branch_name=$(grep -m1 '<!-- batch:' "$file" 2>/dev/null | sed 's/.*branch: \([^ ]*\) -->.*/\1/' || true)
  if [[ -z "$branch_name" || "$branch_name" == *"<!--"* ]]; then
    local slug; slug=$(echo "$title" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g;s/--*/-/g;s/^-//;s/-$//' | cut -c1-50)
    [[ -n "$slug" ]] && branch_name="pipeline/${slug}"
  fi
  rm -f "$file"
  [[ -n "$branch_name" ]] && git -C "$REPO_ROOT" branch -D "$branch_name" 2>/dev/null || true
  rm -f "$LOG_DIR"/*"${fname}"* 2>/dev/null || true
  rm -rf "$REPO_ROOT/builder/tasks/artifacts/${fname}" 2>/dev/null || true
  ACTION_MSG="${RED}Deleted: ${title}${RESET}"
}

action_archive() {
  local done_dir="$TASK_SOURCE/done" archive_dir="$TASK_SOURCE/archive" count=0
  [[ -d "$done_dir" ]] || { ACTION_MSG="${YELLOW}No completed tasks to archive${RESET}"; return; }
  mkdir -p "$archive_dir"
  for f in "$done_dir"/*.md; do
    [[ -f "$f" ]] || continue
    mv "$f" "$archive_dir/$(basename "$f")"; count=$((count + 1))
  done
  [[ $count -eq 0 ]] && { ACTION_MSG="${YELLOW}No completed tasks to archive${RESET}"; return; }
  ACTION_MSG="${GREEN}Archived $count completed task(s)${RESET}"
}

action_start_task() {
  [[ $ALL_TASKS_COUNT -eq 0 || $SELECTED_IDX -ge $ALL_TASKS_COUNT ]] && return
  [[ "${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}" != "todo" ]] && { ACTION_MSG="${YELLOW}Can only start waiting tasks${RESET}"; return; }
  local file="${ALL_TASKS_FILES[$SELECTED_IDX]}" title="${ALL_TASKS_TITLES[$SELECTED_IDX]}"
  local fname; fname=$(basename "$file" .md)
  local worker_num=1
  while [[ -d "$WORKTREE_BASE/worker-${worker_num}" ]]; do worker_num=$((worker_num + 1)); done
  local wt="$WORKTREE_BASE/worker-${worker_num}"
  local slug; slug=$(echo "$title" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g;s/--*/-/g;s/^-//;s/-$//' | cut -c1-50)
  [[ -z "$slug" ]] && slug="$fname"
  local task_branch="pipeline/${slug}"
  mkdir -p "$TASK_SOURCE/in-progress"
  local active_file="$TASK_SOURCE/in-progress/$(basename "$file")"
  mv "$file" "$active_file"
  (
    mkdir -p "$WORKTREE_BASE"
    git -C "$REPO_ROOT" worktree prune 2>/dev/null || true
    git -C "$REPO_ROOT" worktree add --detach "$wt" HEAD 2>/dev/null || { mv "$active_file" "$file"; exit 1; }
    while IFS= read -r dep_dir; do
      local rel_path="${dep_dir#"$REPO_ROOT"/}" wt_target="$wt/${dep_dir#"$REPO_ROOT"/}"
      [[ -L "$wt_target" || -d "$wt_target" ]] && continue
      mkdir -p "$wt/$(dirname "$rel_path")"
      ln -s "$dep_dir" "$wt_target"
    done < <(find "$REPO_ROOT" -maxdepth 3 -type d \( -name vendor -o -name node_modules -o -name var -o -name '.venv' \) -not -path '*/.opencode/*' -not -path '*/.git/*' 2>/dev/null)
    [[ -d "$REPO_ROOT/.local" && ! -L "$wt/.local" ]] && ln -s "$REPO_ROOT/.local" "$wt/.local"
    local artifacts_dir="$REPO_ROOT/builder/tasks/artifacts"; mkdir -p "$artifacts_dir"
    [[ ! -L "$wt/builder/tasks/artifacts" ]] && { mkdir -p "$wt/builder/tasks"; ln -s "$artifacts_dir" "$wt/builder/tasks/artifacts"; }
    local wt_task_file="$wt/.pipeline-task-extra.md"
    cp "$active_file" "$wt_task_file"
    local log_file="$LOG_DIR/extra-worker-${worker_num}-${fname}.log"
    mkdir -p "$(dirname "$log_file")"
    local start_time; start_time=$(date +%s); local exit_code=0
    "$wt/scripts/pipeline.sh" --branch "$task_branch" --task-file "$wt_task_file" > "$log_file" 2>&1 || exit_code=$?
    rm -f "$wt_task_file"
    local duration=$(( $(date +%s) - start_time )) batch_ts; batch_ts=$(date +%Y%m%d_%H%M%S)
    local base_name; base_name=$(basename "$active_file")
    if [[ $exit_code -eq 0 ]]; then
      { echo "<!-- batch: ${batch_ts} | status: pass | duration: ${duration}s | branch: ${task_branch} -->"; cat "$active_file"; } > "$TASK_SOURCE/done/${base_name}"
      rm -f "$TASK_SOURCE/failed/${base_name}"  # clean leftover from previous attempt
    else
      mkdir -p "$TASK_SOURCE/failed"
      { echo "<!-- batch: ${batch_ts} | status: fail | duration: ${duration}s | branch: ${task_branch} -->"; cat "$active_file"; } > "$TASK_SOURCE/failed/${base_name}"
      # Extract summary from pipeline branch (summarizer runs even on failure)
      mkdir -p "$TASK_SOURCE/summary"
      local summary_files
      summary_files=$(git -C "$REPO_ROOT" ls-tree --name-only "${task_branch}:builder/tasks/summary/" 2>/dev/null | grep -v '\.gitkeep$' || true)
      local sf
      for sf in $summary_files; do
        [[ -f "$TASK_SOURCE/summary/$sf" ]] && continue
        git -C "$REPO_ROOT" show "${task_branch}:builder/tasks/summary/$sf" > "$TASK_SOURCE/summary/$sf" 2>/dev/null || true
      done
    fi
    rm -f "$active_file"
    git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || rm -rf "$wt"
  ) &
  ACTION_MSG="${GREEN}Started extra worker-${worker_num}: ${title}${RESET}"
}

# ── Auto-start logic ─────────────────────────────────────────────────
# Called every render cycle. Launches workers when tasks are waiting.
# Respects WORKERS limit and cooldown between starts.
autostart_check() {
  [[ "$AUTOSTART" != "true" ]] && return
  local now; now=$(date +%s)
  (( now - AUTOSTART_LAST < AUTOSTART_COOLDOWN )) && return

  local todo_count; todo_count=$(count_files "$TASK_SOURCE/todo")
  [[ $todo_count -eq 0 ]] && return

  if ! is_batch_running; then
    # No batch running but tasks waiting → start batch
    AUTOSTART_LAST=$now
    local _caff=""; command -v caffeinate &>/dev/null && _caff="caffeinate -s"
    $_caff nohup "$REPO_ROOT/builder/pipeline-batch.sh" \
      --workers "$WORKERS" --no-stop-on-failure --watch "$TASK_SOURCE" \
      > "$REPO_ROOT/batch.log" 2>&1 &
    ACTION_MSG="${GREEN}Auto-started batch ($todo_count tasks, $WORKERS workers, PID $!)${RESET}"
    invalidate_cache
    return
  fi

  # Batch is running — check if we should start extra workers
  # Count currently active workers
  local active=0
  if [[ -d "$WORKTREE_BASE" ]]; then
    for wt in "$WORKTREE_BASE"/worker-*; do
      [[ -d "$wt" ]] || continue
      local wname; wname=$(basename "$wt")
      _worker_is_alive "$wname" && (( active++ ))
    done
  fi
  # Also count the batch process itself as 1 worker
  (( active < 1 )) && active=1

  if (( active < WORKERS && todo_count > 0 )); then
    # More capacity available and tasks waiting → start extra worker for first todo task
    AUTOSTART_LAST=$now
    # Find the first todo task index and temporarily select it
    local saved_idx=$SELECTED_IDX
    local i
    for ((i=0; i<ALL_TASKS_COUNT; i++)); do
      if [[ "${ALL_TASKS_STATES[$i]%%:*}" == "todo" ]]; then
        SELECTED_IDX=$i
        action_start_task
        break
      fi
    done
    SELECTED_IDX=$saved_idx
    invalidate_cache
  fi
}

# ── Main render dispatch ──────────────────────────────────────────────
render() {
  if [[ $CURRENT_TAB -eq 1 ]]; then
    render_overview
  elif [[ $CURRENT_TAB -eq 2 ]]; then
    render_logs_tab
  else
    # DETECTED_WORKERS is set by update_worker_state inside render_worker_tab
    local worker_idx=$((CURRENT_TAB - 2))
    if [[ ${#DETECTED_WORKERS[@]} -gt 0 && $worker_idx -le ${#DETECTED_WORKERS[@]} ]]; then
      local worker_num="${DETECTED_WORKERS[$((worker_idx - 1))]#worker-}"
      render_worker_tab "$worker_num"
    else
      render_logs_tab
      CURRENT_TAB=2
    fi
  fi
}

# ── Input handling ────────────────────────────────────────────────────
#
# Uses dd(1) to read single bytes from stdin. This is the most reliable
# method on macOS bash 3.2 where `read -rsn1` has issues with escape
# sequences in raw terminal mode.
#
LAST_KEY=""

read_single_byte() {
  # dd reads exactly 1 byte; timeout via TERM_TIMEOUT_PID
  local byte=""
  byte=$(dd bs=1 count=1 2>/dev/null) || true
  printf '%s' "$byte"
}

read_key() {
  LAST_KEY="_timeout_"

  # Use read with timeout for the first byte (acts as our poll interval)
  local key=""
  if ! IFS= read -rsn1 -t "$REFRESH_INTERVAL" key 2>/dev/null; then
    return  # timeout — no key pressed
  fi

  # Got a byte
  if [[ "$key" == $'\x1b' ]]; then
    # Escape sequence — read 2 more bytes quickly via dd with timeout
    local seq=""
    seq=$(dd bs=2 count=1 2>/dev/null < /dev/stdin) || true
    case "$seq" in
      "[A") LAST_KEY="UP" ;;
      "[B") LAST_KEY="DOWN" ;;
      "[C") LAST_KEY="RIGHT" ;;
      "[D") LAST_KEY="LEFT" ;;
      *)    LAST_KEY="ESC" ;;
    esac
  elif [[ -z "$key" || "$key" == $'\r' || "$key" == $'\n' ]]; then
    LAST_KEY="ENTER"
  else
    LAST_KEY="$key"
  fi
}

# ── Main loop ─────────────────────────────────────────────────────────
main() {
  # Alternate screen buffer
  printf '\033[?1049h'
  tput civis 2>/dev/null || true

  # Raw terminal mode
  ORIG_STTY=$(stty -g 2>/dev/null || true)
  stty -echo -icanon 2>/dev/null || true

  cleanup() {
    stty "$ORIG_STTY" 2>/dev/null || true
    tput cnorm 2>/dev/null || true
    printf '\033[?1049l'
  }
  trap 'cleanup; exit 0' EXIT INT TERM

  # One-time startup tasks
  cleanup_old_logs

  while true; do
    render
    autostart_check
    RENDER_CYCLE=$((RENDER_CYCLE + 1))
    FORCE_REBUILD=false
    read_key

    case "$LAST_KEY" in
      q|Q)
        if [[ "$LOG_VIEW_MODE" == true ]]; then
          LOG_VIEW_MODE=false; LOG_VIEW_FILE=""
        elif [[ "$DETAIL_MODE" == true ]]; then
          DETAIL_MODE=false; DETAIL_FILE=""
        else
          exit 0
        fi ;;
      r|R)        invalidate_cache; continue ;;
      s|S)        action_start; invalidate_cache ;;
      f|F)        action_retry_failed; invalidate_cache ;;
      k|K)        action_kill; invalidate_cache ;;
      x|X)        action_stop_task; invalidate_cache ;;
      +)          action_promote; invalidate_cache ;;
      -)          action_demote; invalidate_cache ;;
      d|D)        action_delete; invalidate_cache ;;
      a|A)        action_archive; invalidate_cache ;;
      l|L)
        if [[ $CURRENT_TAB -eq 1 && $ALL_TASKS_COUNT -gt 0 && $SELECTED_IDX -lt $ALL_TASKS_COUNT ]]; then
          local state="${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}"
          if [[ "$state" == "failed" || "$state" == "in-progress" ]]; then
            LOG_VIEW_FILE=$(find_task_log "${ALL_TASKS_FILES[$SELECTED_IDX]}")
            LOG_VIEW_MODE=true
          else
            ACTION_MSG="${YELLOW}Logs available for failed/in-progress tasks only${RESET}"
          fi
        fi ;;
      UP)         [[ $SELECTED_IDX -gt 0 ]] && SELECTED_IDX=$((SELECTED_IDX - 1)); DETAIL_MODE=false; LOG_VIEW_MODE=false ;;
      DOWN)       [[ $SELECTED_IDX -lt $((ALL_TASKS_COUNT - 1)) ]] && SELECTED_IDX=$((SELECTED_IDX + 1)); DETAIL_MODE=false; LOG_VIEW_MODE=false ;;
      LEFT)       CURRENT_TAB=$(( CURRENT_TAB > 1 ? CURRENT_TAB - 1 : MAX_TABS )); DETAIL_MODE=false; LOG_VIEW_MODE=false ;;
      RIGHT)      CURRENT_TAB=$(( CURRENT_TAB < MAX_TABS ? CURRENT_TAB + 1 : 1 )); DETAIL_MODE=false; LOG_VIEW_MODE=false ;;
      ESC|$'\x7f') DETAIL_MODE=false; DETAIL_FILE=""; LOG_VIEW_MODE=false; LOG_VIEW_FILE="" ;;
      ENTER)
        if [[ $CURRENT_TAB -eq 1 && $ALL_TASKS_COUNT -gt 0 && $SELECTED_IDX -lt $ALL_TASKS_COUNT ]]; then
          DETAIL_MODE=true; DETAIL_FILE="${ALL_TASKS_FILES[$SELECTED_IDX]}"
        fi ;;
      [1-9])
        if [[ "$LAST_KEY" -le $MAX_TABS ]]; then
          CURRENT_TAB=$LAST_KEY; DETAIL_MODE=false
        fi ;;
    esac
  done
}

main
