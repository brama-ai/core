#!/usr/bin/env bash
#
# Interactive pipeline batch monitor with tab-based TUI.
#
# Usage:
#   ./scripts/pipeline-monitor.sh              # auto-detect latest batch
#   ./scripts/pipeline-monitor.sh tasks/       # monitor specific tasks folder
#
# Tabs:
#   [1] Overview   — task statuses, progress bar, timing
#   [2] Worker 1   — live log tail for worker-1
#   [3] Worker 2   — live log tail for worker-2
#   ...
#
# Keys:
#   1-9       Switch tabs
#   q/Ctrl-C  Quit
#   r         Refresh (Overview tab)
#   s         Start batch (run todo tasks with caffeinate)
#   f         Retry failed (move failed→todo, delete branches, start)
#   k         Kill running batch
#
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TASK_SOURCE="${1:-$REPO_ROOT/tasks}"
WORKTREE_BASE="$REPO_ROOT/.opencode/pipeline/worktrees"
LOG_DIR="$REPO_ROOT/.opencode/pipeline/logs"
REPORT_DIR="$REPO_ROOT/.opencode/pipeline/reports"

# Colors (tput-based for compatibility)
if command -v tput &>/dev/null && [[ -t 1 ]]; then
  BOLD=$(tput bold)
  DIM=$(tput dim)
  REV=$(tput rev)
  RESET=$(tput sgr0)
  RED=$(tput setaf 1)
  GREEN=$(tput setaf 2)
  YELLOW=$(tput setaf 3)
  BLUE=$(tput setaf 4)
  CYAN=$(tput setaf 6)
  WHITE=$(tput setaf 7)
else
  BOLD='' DIM='' REV='' RESET=''
  RED='' GREEN='' YELLOW='' BLUE='' CYAN='' WHITE=''
fi

CURRENT_TAB=1
MAX_TABS=1  # will be updated based on active workers

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

count_files() {
  local dir="$1"
  if [[ -d "$dir" ]]; then
    find "$dir" -maxdepth 1 -name '*.md' -not -name '.gitkeep' 2>/dev/null | wc -l | tr -d ' '
  else
    echo "0"
  fi
}

get_terminal_size() {
  TERM_ROWS=$(tput lines 2>/dev/null || echo 24)
  TERM_COLS=$(tput cols 2>/dev/null || echo 80)
}

# Draw a horizontal line
hline() {
  local ch="${1:-─}"
  printf '%*s' "$TERM_COLS" '' | tr ' ' "$ch"
}

# Draw progress bar
progress_bar() {
  local done="$1"
  local total="$2"
  local width=$((TERM_COLS - 20))
  [[ $width -lt 10 ]] && width=10

  if [[ $total -eq 0 ]]; then
    printf "[%*s] 0/0" "$width" ""
    return
  fi

  local filled=$(( done * width / total ))
  local empty=$(( width - filled ))

  printf "${GREEN}["
  printf '%*s' "$filled" '' | tr ' ' '█'
  printf '%*s' "$empty" '' | tr ' ' '░'
  printf "]${RESET} %d/%d" "$done" "$total"
}

# Format seconds to human-readable
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

# Detect active workers
detect_workers() {
  local workers=()
  if [[ -d "$WORKTREE_BASE" ]]; then
    for wt in "$WORKTREE_BASE"/worker-*; do
      [[ -d "$wt" ]] && workers+=("$(basename "$wt")")
    done
  fi
  echo "${workers[@]:-}"
}

# Find the most recent log file for a worker
find_worker_log() {
  local worker="$1"
  local wt_log_dir="$WORKTREE_BASE/$worker/.opencode/pipeline/logs"

  # Check worktree-local logs first
  if [[ -d "$wt_log_dir" ]]; then
    ls -t "$wt_log_dir"/*.log 2>/dev/null | head -1
    return
  fi

  # Fallback to main repo logs
  ls -t "$LOG_DIR"/*.log 2>/dev/null | head -1
}

# Get task currently in a worker's pipeline.sh (from the log filename or process)
get_worker_task() {
  local worker="$1"
  local log_file
  log_file=$(find_worker_log "$worker")

  if [[ -n "$log_file" ]]; then
    # Log filename format: TIMESTAMP_AGENT.log
    local agent
    agent=$(basename "$log_file" .log | sed 's/^[0-9_]*//')
    # Get the task from in-progress folder or process
    echo "$agent"
  fi
}

# ---------------------------------------------------------------------------
# Tab: Overview
# ---------------------------------------------------------------------------
render_overview() {
  get_terminal_size
  clear

  local todo_count in_progress_count done_count failed_count total
  todo_count=$(count_files "$TASK_SOURCE/todo")
  in_progress_count=$(count_files "$TASK_SOURCE/in-progress")
  done_count=$(count_files "$TASK_SOURCE/done")
  failed_count=$(count_files "$TASK_SOURCE/failed")
  total=$((todo_count + in_progress_count + done_count + failed_count))
  local completed=$((done_count + failed_count))

  # Header
  echo "${CYAN}${BOLD}  Pipeline Monitor${RESET}  $(date '+%H:%M:%S')"
  echo "${DIM}$(hline)${RESET}"

  # Tab bar
  render_tabs

  echo ""

  # Progress bar
  printf "  "
  progress_bar "$completed" "$total"
  echo ""
  echo ""

  # Status cards
  printf "  ${BLUE}${BOLD}⏳ Todo:${RESET}        %-4d" "$todo_count"
  printf "  ${YELLOW}${BOLD}🔄 In Progress:${RESET} %-4d" "$in_progress_count"
  printf "  ${GREEN}${BOLD}✓ Done:${RESET}        %-4d" "$done_count"
  printf "  ${RED}${BOLD}✗ Failed:${RESET}      %-4d" "$failed_count"
  echo ""
  echo ""

  # Batch timing
  local batch_pid
  batch_pid=$(pgrep -f 'pipeline-batch.sh' 2>/dev/null | head -1 || true)
  if [[ -n "$batch_pid" ]]; then
    local batch_start
    batch_start=$(ps -o lstart= -p "$batch_pid" 2>/dev/null | xargs -I{} date -jf '%c' '{}' '+%s' 2>/dev/null || echo "")
    if [[ -n "$batch_start" ]]; then
      local now elapsed
      now=$(date +%s)
      elapsed=$((now - batch_start))
      echo "  ${BOLD}Status:${RESET} ${GREEN}Running${RESET}  ($(format_duration "$elapsed") elapsed, PID $batch_pid)"
    else
      echo "  ${BOLD}Status:${RESET} ${GREEN}Running${RESET}  (PID $batch_pid)"
    fi
  else
    echo "  ${BOLD}Status:${RESET} ${DIM}Not running${RESET}"
  fi
  echo ""

  echo "${DIM}$(hline)${RESET}"

  # In-progress tasks detail
  if [[ -d "$TASK_SOURCE/in-progress" ]]; then
    local ip_files
    ip_files=$(find "$TASK_SOURCE/in-progress" -maxdepth 1 -name '*.md' 2>/dev/null)
    if [[ -n "$ip_files" ]]; then
      echo "  ${YELLOW}${BOLD}In Progress:${RESET}"
      while IFS= read -r f; do
        local title
        title=$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)
        # Check which stage is running from logs
        local stage="..."
        local fname
        fname=$(basename "$f" .md)
        local latest_log
        latest_log=$(ls -t "$LOG_DIR"/*"${fname}"* "$WORKTREE_BASE"/worker-*/.opencode/pipeline/logs/* 2>/dev/null | head -1 || true)
        if [[ -n "$latest_log" ]]; then
          stage=$(basename "$latest_log" .log | sed 's/^[0-9_]*//')
        fi
        echo "    ${YELLOW}▸${RESET} $title ${DIM}[$stage]${RESET}"
      done <<< "$ip_files"
      echo ""
    fi
  fi

  # Done tasks
  if [[ -d "$TASK_SOURCE/done" ]]; then
    local done_files
    done_files=$(find "$TASK_SOURCE/done" -maxdepth 1 -name '*.md' 2>/dev/null)
    if [[ -n "$done_files" ]]; then
      echo "  ${GREEN}${BOLD}Completed:${RESET}"
      while IFS= read -r f; do
        local title duration branch
        title=$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)
        duration=$(grep -m1 '<!-- batch:' "$f" 2>/dev/null | sed 's/.*duration: \([0-9]*\)s.*/\1/' || echo "?")
        branch=$(grep -m1 '<!-- batch:' "$f" 2>/dev/null | sed 's/.*branch: \([^ ]*\) -->.*/\1/' || echo "?")
        if [[ "$duration" =~ ^[0-9]+$ ]]; then
          echo "    ${GREEN}✓${RESET} $title ${DIM}($(format_duration "$duration"), $branch)${RESET}"
        else
          echo "    ${GREEN}✓${RESET} $title"
        fi
      done <<< "$done_files"
      echo ""
    fi
  fi

  # Failed tasks
  if [[ -d "$TASK_SOURCE/failed" ]]; then
    local fail_files
    fail_files=$(find "$TASK_SOURCE/failed" -maxdepth 1 -name '*.md' 2>/dev/null)
    if [[ -n "$fail_files" ]]; then
      echo "  ${RED}${BOLD}Failed:${RESET}"
      while IFS= read -r f; do
        local title duration
        title=$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)
        duration=$(grep -m1 '<!-- batch:' "$f" 2>/dev/null | sed 's/.*duration: \([0-9]*\)s.*/\1/' || echo "?")
        if [[ "$duration" =~ ^[0-9]+$ ]]; then
          echo "    ${RED}✗${RESET} $title ${DIM}($(format_duration "$duration"))${RESET}"
        else
          echo "    ${RED}✗${RESET} $title"
        fi
      done <<< "$fail_files"
      echo ""
    fi
  fi

  # Todo tasks
  if [[ -d "$TASK_SOURCE/todo" ]]; then
    local todo_files
    todo_files=$(find "$TASK_SOURCE/todo" -maxdepth 1 -name '*.md' 2>/dev/null)
    if [[ -n "$todo_files" ]]; then
      echo "  ${BLUE}${BOLD}Waiting:${RESET}"
      while IFS= read -r f; do
        local title
        title=$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)
        echo "    ${DIM}○${RESET} $title"
      done <<< "$todo_files"
      echo ""
    fi
  fi

  echo "${DIM}$(hline)${RESET}"

  # Action message (shown for one refresh cycle)
  if [[ -n "$ACTION_MSG" ]]; then
    echo "  $ACTION_MSG"
    ACTION_MSG=""
  fi

  echo "  ${DIM}Keys: [1-$MAX_TABS] tabs  [s] start  [f] retry failed  [k] kill  [r] refresh  [q] quit${RESET}"
}

# ---------------------------------------------------------------------------
# Tab: Worker log
# ---------------------------------------------------------------------------
render_worker_tab() {
  local worker_id="$1"
  local worker_name="worker-${worker_id}"

  get_terminal_size
  clear

  echo "${CYAN}${BOLD}  Pipeline Monitor${RESET}  ${DIM}$worker_name${RESET}  $(date '+%H:%M:%S')"
  echo "${DIM}$(hline)${RESET}"

  render_tabs

  echo ""

  # Find the most recent log for this worker
  local wt_log_dir="$WORKTREE_BASE/$worker_name/.opencode/pipeline/logs"
  local log_file=""

  if [[ -d "$wt_log_dir" ]]; then
    log_file=$(ls -t "$wt_log_dir"/*.log 2>/dev/null | head -1 || true)
  fi

  if [[ -z "$log_file" ]]; then
    echo "  ${DIM}No active log found for $worker_name${RESET}"
    echo ""
    echo "  ${DIM}Log directory: $wt_log_dir${RESET}"
    echo ""
    echo "${DIM}$(hline)${RESET}"
    echo "  ${DIM}Keys: [1-$MAX_TABS] switch tab  [r] refresh  [q] quit${RESET}"
    return
  fi

  local agent_name
  agent_name=$(basename "$log_file" .log | sed 's/^[0-9_]*//')
  local log_size
  log_size=$(wc -c < "$log_file" | tr -d ' ')

  echo "  ${BOLD}Agent:${RESET} ${YELLOW}$agent_name${RESET}    ${BOLD}Log:${RESET} ${DIM}$(basename "$log_file")${RESET}    ${BOLD}Size:${RESET} ${DIM}$(( log_size / 1024 ))KB${RESET}"
  echo "${DIM}$(hline)${RESET}"

  # Show last N lines of log
  local available_lines=$((TERM_ROWS - 8))
  [[ $available_lines -lt 5 ]] && available_lines=5

  tail -n "$available_lines" "$log_file" 2>/dev/null | while IFS= read -r line; do
    # Colorize common patterns
    if [[ "$line" == *"error"* || "$line" == *"Error"* || "$line" == *"FAIL"* ]]; then
      echo "  ${RED}$line${RESET}"
    elif [[ "$line" == *"✓"* || "$line" == *"PASS"* || "$line" == *"success"* ]]; then
      echo "  ${GREEN}$line${RESET}"
    elif [[ "$line" == *"──"* || "$line" == *"═══"* ]]; then
      echo "  ${CYAN}$line${RESET}"
    else
      echo "  $line"
    fi
  done

  # Position cursor at bottom
  echo ""
  echo "${DIM}$(hline)${RESET}"
  echo "  ${DIM}Keys: [1-$MAX_TABS] tabs  [s] start  [f] retry failed  [k] kill  [q] quit  (auto-refresh 3s)${RESET}"
}

# ---------------------------------------------------------------------------
# Actions: start, retry, kill
# ---------------------------------------------------------------------------

WORKERS="${MONITOR_WORKERS:-2}"

is_batch_running() {
  pgrep -f 'pipeline-batch.sh' &>/dev/null
}

action_start() {
  if is_batch_running; then
    ACTION_MSG="${RED}Batch already running${RESET}"
    return
  fi

  local todo_count
  todo_count=$(count_files "$TASK_SOURCE/todo")
  if [[ $todo_count -eq 0 ]]; then
    ACTION_MSG="${YELLOW}No tasks in todo/${RESET}"
    return
  fi

  caffeinate -s nohup "$REPO_ROOT/scripts/pipeline-batch.sh" \
    --workers "$WORKERS" --no-stop-on-failure --watch "$TASK_SOURCE" \
    > "$REPO_ROOT/batch.log" 2>&1 &

  ACTION_MSG="${GREEN}Started batch ($todo_count tasks, $WORKERS workers, PID $!)${RESET}"
}

action_retry_failed() {
  if is_batch_running; then
    ACTION_MSG="${RED}Batch running — kill first (k)${RESET}"
    return
  fi

  local fail_dir="$TASK_SOURCE/failed"
  local todo_dir="$TASK_SOURCE/todo"
  local count=0

  if [[ ! -d "$fail_dir" ]]; then
    ACTION_MSG="${YELLOW}No failed/ directory${RESET}"
    return
  fi

  mkdir -p "$todo_dir"

  for f in "$fail_dir"/*.md; do
    [[ -f "$f" ]] || continue

    # Delete the corresponding pipeline branch
    local title
    title=$(grep -m1 '<!-- batch:' "$f" 2>/dev/null | sed 's/.*branch: \([^ ]*\) -->.*/\1/' || true)
    if [[ -n "$title" && "$title" != "$(cat "$f")" ]]; then
      git -C "$REPO_ROOT" branch -D "$title" 2>/dev/null || true
    fi

    # Strip batch metadata and move to todo
    sed '/^<!-- batch:.*-->$/d' "$f" > "$todo_dir/$(basename "$f")"
    rm -f "$f"
    count=$((count + 1))
  done

  if [[ $count -eq 0 ]]; then
    ACTION_MSG="${YELLOW}No failed tasks to retry${RESET}"
    return
  fi

  # Auto-start
  action_start
  ACTION_MSG="${GREEN}Moved $count failed→todo and started batch${RESET}"
}

action_kill() {
  local pids
  pids=$(pgrep -f 'pipeline-batch.sh' 2>/dev/null || true)
  if [[ -z "$pids" ]]; then
    ACTION_MSG="${YELLOW}No batch running${RESET}"
    return
  fi

  # Kill batch and all child opencode processes
  pkill -f 'pipeline-batch.sh' 2>/dev/null || true
  pkill -f 'opencode run --agent' 2>/dev/null || true
  ACTION_MSG="${RED}Killed batch processes${RESET}"
}

ACTION_MSG=""

# ---------------------------------------------------------------------------
# Tab bar
# ---------------------------------------------------------------------------
render_tabs() {
  local workers
  workers=($(detect_workers))
  MAX_TABS=$((1 + ${#workers[@]}))

  printf "  "

  # Tab 1: Overview
  if [[ $CURRENT_TAB -eq 1 ]]; then
    printf "${REV}${BOLD} 1:Overview ${RESET}"
  else
    printf "${DIM} 1:Overview ${RESET}"
  fi

  # Worker tabs
  local idx=2
  for w in "${workers[@]}"; do
    if [[ $CURRENT_TAB -eq $idx ]]; then
      printf "${REV}${BOLD} ${idx}:${w} ${RESET}"
    else
      printf "${DIM} ${idx}:${w} ${RESET}"
    fi
    idx=$((idx + 1))
  done

  echo ""
}

# ---------------------------------------------------------------------------
# Main render
# ---------------------------------------------------------------------------
render() {
  if [[ $CURRENT_TAB -eq 1 ]]; then
    render_overview
  else
    local workers
    workers=($(detect_workers))
    local worker_idx=$((CURRENT_TAB - 1))
    if [[ $worker_idx -le ${#workers[@]} ]]; then
      # Extract worker number from worker name (e.g., worker-2 → 2)
      local worker_num
      worker_num=$(echo "${workers[$((worker_idx - 1))]}" | sed 's/worker-//')
      render_worker_tab "$worker_num"
    else
      render_overview
      CURRENT_TAB=1
    fi
  fi
}

# ---------------------------------------------------------------------------
# Main loop
# ---------------------------------------------------------------------------
main() {
  # Hide cursor
  tput civis 2>/dev/null || true

  # Restore cursor on exit
  trap 'tput cnorm 2>/dev/null; echo ""; exit 0' EXIT INT TERM

  # Auto-refresh interval (seconds)
  local REFRESH_INTERVAL=3

  while true; do
    render

    # Wait for keypress or timeout
    local key=""
    if read -rsn1 -t "$REFRESH_INTERVAL" key 2>/dev/null; then
      case "$key" in
        q|Q)
          exit 0
          ;;
        r|R)
          continue
          ;;
        s|S)
          action_start
          ;;
        f|F)
          action_retry_failed
          ;;
        k|K)
          action_kill
          ;;
        [1-9])
          if [[ "$key" -le $MAX_TABS ]]; then
            CURRENT_TAB=$key
          fi
          ;;
      esac
    fi
  done
}

main
