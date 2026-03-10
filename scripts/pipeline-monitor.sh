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
#   ←/→ or 1-9  Switch tabs
#   ↑/↓         Select task (Overview tab)
#   Enter       View selected task detail
#   Esc/Bksp    Back to task list
#   s           Start batch (run todo tasks with caffeinate)
#   f           Retry failed (move failed→todo, delete branches, start)
#   k           Kill running batch
#   x           Stop selected in-progress task
#   p           Promote selected todo task (increase priority)
#   d           Demote selected todo task (decrease priority)
#   r           Refresh
#   q/Ctrl-C    Quit
#
# Task priority:
#   Tasks in todo/ are sorted by priority. Priority is set via a comment
#   in the first line of the .md file:
#     <!-- priority: 5 -->
#   Higher number = higher priority. Default priority = 1.
#   Use [p] and [d] keys to adjust priority of the selected todo task.
#
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TASK_SOURCE="${1:-$REPO_ROOT/tasks}"
# Support both old and new worktree locations
if [[ -d "$REPO_ROOT/.pipeline-worktrees" ]]; then
  WORKTREE_BASE="$REPO_ROOT/.pipeline-worktrees"
else
  WORKTREE_BASE="$REPO_ROOT/.opencode/pipeline/worktrees"
fi
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
  MAGENTA=$(tput setaf 5)
else
  BOLD='' DIM='' REV='' RESET=''
  RED='' GREEN='' YELLOW='' BLUE='' CYAN='' WHITE='' MAGENTA=''
fi

CURRENT_TAB=1
MAX_TABS=1  # updated based on active workers
SELECTED_IDX=0  # cursor position in task list
DETAIL_MODE=false  # true = viewing task detail
DETAIL_FILE=""  # file path of task being viewed

# ---------------------------------------------------------------------------
# Flicker-free rendering — buffer then blit
# ---------------------------------------------------------------------------
# ESC and CLR are real escape characters, not string literals
ESC=$'\033'
CLR="${ESC}[2K"

RENDER_BUF=""
PREV_LINE_COUNT=0

buf_reset() { RENDER_BUF=""; }
buf_line()  { RENDER_BUF+="${CLR}${1}"$'\n'; }
buf_flush() {
  local cur_lines
  cur_lines=$(echo "$RENDER_BUF" | wc -l | tr -d ' ')

  # Move cursor to top-left
  printf '%s[H' "$ESC"

  # Write the buffered content
  printf '%s' "$RENDER_BUF"

  # Erase leftover lines from previous frame
  local i
  for ((i=cur_lines; i<PREV_LINE_COUNT; i++)); do
    printf '%s\n' "$CLR"
  done

  # Move cursor back after clearing old lines
  if [[ $cur_lines -lt $PREV_LINE_COUNT ]]; then
    printf '%s[%dA' "$ESC" "$((PREV_LINE_COUNT - cur_lines))"
  fi

  PREV_LINE_COUNT=$cur_lines
}

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

hline() {
  local ch="${1:-─}"
  printf '%*s' "$TERM_COLS" '' | tr ' ' "$ch"
}

progress_bar_str() {
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

# ---------------------------------------------------------------------------
# Priority system
# ---------------------------------------------------------------------------

# Extract priority from task file. Looks for <!-- priority: N --> in first line.
# Default = 1
get_priority() {
  local file="$1"
  local prio
  prio=$(head -1 "$file" 2>/dev/null | sed -n 's/.*<!-- *priority: *\([0-9]*\) *-->.*/\1/p')
  echo "${prio:-1}"
}

# Set priority on a task file
set_priority() {
  local file="$1"
  local prio="$2"
  local first_line
  first_line=$(head -1 "$file" 2>/dev/null)

  if [[ "$first_line" =~ \<!--\ *priority: ]]; then
    # Replace existing priority
    sed -i.bak "1s/<!-- *priority: *[0-9]* *-->/<!-- priority: ${prio} -->/" "$file"
    rm -f "${file}.bak"
  else
    # Prepend priority line
    local tmp
    tmp=$(mktemp)
    echo "<!-- priority: ${prio} -->" > "$tmp"
    cat "$file" >> "$tmp"
    mv "$tmp" "$file"
  fi
}

# List todo files sorted by priority (highest first), then by name
list_todo_sorted() {
  local todo_dir="$TASK_SOURCE/todo"
  [[ -d "$todo_dir" ]] || return

  local entries=()
  local ecount=0
  while IFS= read -r f; do
    [[ -f "$f" ]] || continue
    local prio
    prio=$(get_priority "$f")
    entries+=("$(printf '%03d|%s' "$prio" "$f")")
    ecount=$((ecount + 1))
  done < <(find "$todo_dir" -maxdepth 1 -name '*.md' 2>/dev/null | sort)

  # Sort by priority descending (higher = first)
  [[ $ecount -gt 0 ]] && \
    printf '%s\n' "${entries[@]}" | sort -t'|' -k1 -rn | cut -d'|' -f2
}

# ---------------------------------------------------------------------------
# Worker detection — distinguish active vs queued
# ---------------------------------------------------------------------------

detect_workers() {
  local workers=()
  local wcount=0
  if [[ -d "$WORKTREE_BASE" ]]; then
    for wt in "$WORKTREE_BASE"/worker-*; do
      [[ -d "$wt" ]] && { workers+=("$(basename "$wt")"); wcount=$((wcount + 1)); }
    done
  fi
  if [[ $wcount -gt 0 ]]; then
    echo "${workers[*]}"
  fi
}

# Get the task a specific worker is currently running (from opencode process)
get_worker_active_task() {
  local worker="$1"
  local wt_path="$WORKTREE_BASE/$worker"

  # Check if pipeline.sh is running in this worktree
  local pid
  pid=$(pgrep -f "pipeline.sh.*${worker}" 2>/dev/null | head -1 || true)
  if [[ -z "$pid" ]]; then
    # Try matching by worktree path in process args
    pid=$(pgrep -f "${wt_path}" 2>/dev/null | head -1 || true)
  fi

  if [[ -n "$pid" ]]; then
    # Find the task file being processed
    local task_file
    task_file=$(ps -o args= -p "$pid" 2>/dev/null | grep -oE '\-\-task-file [^ ]+' | awk '{print $2}')
    if [[ -n "$task_file" && -f "$task_file" ]]; then
      grep -m1 '^# ' "$task_file" 2>/dev/null | sed 's/^# //'
      return
    fi
  fi

  # Fallback: check latest log in worktree
  local wt_log_dir="$wt_path/.opencode/pipeline/logs"
  local log_file=""
  if [[ -d "$wt_log_dir" ]]; then
    log_file=$(ls -t "$wt_log_dir"/*.log 2>/dev/null | head -1 || true)
  fi

  if [[ -n "$log_file" ]]; then
    local agent_name
    agent_name=$(basename "$log_file" .log | sed 's/^[0-9_]*//')
    echo "[${agent_name}]"
  fi
}

find_worker_log() {
  local worker="$1"
  local wt_log_dir="$WORKTREE_BASE/$worker/.opencode/pipeline/logs"

  if [[ -d "$wt_log_dir" ]]; then
    ls -t "$wt_log_dir"/*.log 2>/dev/null | head -1
    return
  fi

  ls -t "$LOG_DIR"/*.log 2>/dev/null | head -1
}

# Build arrays of all tasks for cursor navigation
# Sets: ALL_TASKS_FILES[], ALL_TASKS_TITLES[], ALL_TASKS_STATES[], ALL_TASKS_COUNT
build_task_list() {
  ALL_TASKS_FILES=()
  ALL_TASKS_TITLES=()
  ALL_TASKS_STATES=()

  # In-progress
  if [[ -d "$TASK_SOURCE/in-progress" ]]; then
    while IFS= read -r f; do
      [[ -f "$f" ]] || continue
      ALL_TASKS_FILES+=("$f")
      ALL_TASKS_TITLES+=("$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)")
      ALL_TASKS_STATES+=("in-progress")
    done < <(find "$TASK_SOURCE/in-progress" -maxdepth 1 -name '*.md' 2>/dev/null | sort)
  fi

  # Done
  if [[ -d "$TASK_SOURCE/done" ]]; then
    while IFS= read -r f; do
      [[ -f "$f" ]] || continue
      ALL_TASKS_FILES+=("$f")
      ALL_TASKS_TITLES+=("$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)")
      ALL_TASKS_STATES+=("done")
    done < <(find "$TASK_SOURCE/done" -maxdepth 1 -name '*.md' 2>/dev/null | sort)
  fi

  # Failed
  if [[ -d "$TASK_SOURCE/failed" ]]; then
    while IFS= read -r f; do
      [[ -f "$f" ]] || continue
      ALL_TASKS_FILES+=("$f")
      ALL_TASKS_TITLES+=("$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)")
      ALL_TASKS_STATES+=("failed")
    done < <(find "$TASK_SOURCE/failed" -maxdepth 1 -name '*.md' 2>/dev/null | sort)
  fi

  # Todo (sorted by priority)
  while IFS= read -r f; do
    [[ -f "$f" ]] || continue
    local prio
    prio=$(get_priority "$f")
    local title
    title=$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)
    ALL_TASKS_FILES+=("$f")
    ALL_TASKS_TITLES+=("${title}")
    ALL_TASKS_STATES+=("todo:${prio}")
  done < <(list_todo_sorted)

  ALL_TASKS_COUNT=${#ALL_TASKS_FILES[@]}

  # Clamp selected index
  if [[ $SELECTED_IDX -ge $ALL_TASKS_COUNT ]]; then
    SELECTED_IDX=$((ALL_TASKS_COUNT > 0 ? ALL_TASKS_COUNT - 1 : 0))
  fi
}

# ---------------------------------------------------------------------------
# Tab bar with arrow key support
# ---------------------------------------------------------------------------
render_tabs_str() {
  local workers=()
  local raw
  raw=$(detect_workers)
  [[ -n "$raw" ]] && read -ra workers <<< "$raw"
  MAX_TABS=$((1 + ${#workers[@]}))

  local out="  "

  if [[ $CURRENT_TAB -eq 1 ]]; then
    out+="${REV}${BOLD} 1:Overview ${RESET}"
  else
    out+="${DIM} 1:Overview ${RESET}"
  fi

  if [[ ${#workers[@]} -gt 0 ]]; then
    local idx=2
    for w in "${workers[@]}"; do
      local task_hint
      task_hint=$(get_worker_active_task "$w")
      local label="${idx}:${w}"
      [[ -n "$task_hint" ]] && label="${idx}:${w} ${task_hint}"

      if [[ $CURRENT_TAB -eq $idx ]]; then
        out+="${REV}${BOLD} ${label} ${RESET}"
      else
        out+="${DIM} ${label} ${RESET}"
      fi
      idx=$((idx + 1))
    done
  fi

  printf '%s' "$out"
}

# ---------------------------------------------------------------------------
# Tab: Overview
# ---------------------------------------------------------------------------
render_overview() {
  get_terminal_size
  buf_reset

  build_task_list

  local todo_count in_progress_count done_count failed_count total
  todo_count=$(count_files "$TASK_SOURCE/todo")
  in_progress_count=$(count_files "$TASK_SOURCE/in-progress")
  done_count=$(count_files "$TASK_SOURCE/done")
  failed_count=$(count_files "$TASK_SOURCE/failed")
  total=$((todo_count + in_progress_count + done_count + failed_count))
  local completed=$((done_count + failed_count))

  # Header
  buf_line "${CYAN}${BOLD}  Pipeline Monitor${RESET}  $(date '+%H:%M:%S')"
  buf_line "${DIM}$(hline)${RESET}"

  buf_line "$(render_tabs_str)"

  buf_line ""

  # If in detail mode, render task detail instead
  if [[ "$DETAIL_MODE" == true && -n "$DETAIL_FILE" && -f "$DETAIL_FILE" ]]; then
    render_task_detail_buf
    buf_flush
    return
  fi

  # Progress bar
  buf_line "  $(progress_bar_str "$completed" "$total")"
  buf_line ""

  # Status cards
  buf_line "$(printf "  ${BLUE}${BOLD}⏳ Todo:${RESET}        %-4d  ${YELLOW}${BOLD}🔄 In Progress:${RESET} %-4d  ${GREEN}${BOLD}✓ Done:${RESET}        %-4d  ${RED}${BOLD}✗ Failed:${RESET}      %-4d" "$todo_count" "$in_progress_count" "$done_count" "$failed_count")"
  buf_line ""

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
      buf_line "  ${BOLD}Status:${RESET} ${GREEN}Running${RESET}  ($(format_duration "$elapsed") elapsed, PID $batch_pid)"
    else
      buf_line "  ${BOLD}Status:${RESET} ${GREEN}Running${RESET}  (PID $batch_pid)"
    fi
  else
    buf_line "  ${BOLD}Status:${RESET} ${DIM}Not running${RESET}"
  fi
  buf_line ""

  buf_line "${DIM}$(hline)${RESET}"

  # Render task list with cursor
  local available_lines=$((TERM_ROWS - 18))
  [[ $available_lines -lt 5 ]] && available_lines=5

  # Calculate scroll window
  local scroll_start=0
  if [[ $SELECTED_IDX -ge $available_lines ]]; then
    scroll_start=$((SELECTED_IDX - available_lines + 1))
  fi

  local prev_state=""

  for ((i=0; i<ALL_TASKS_COUNT; i++)); do
    if [[ $i -lt $scroll_start ]]; then
      continue
    fi
    if [[ $((i - scroll_start)) -ge $available_lines ]]; then
      buf_line "  ${DIM}  ... $((ALL_TASKS_COUNT - i)) more${RESET}"
      break
    fi

    local state="${ALL_TASKS_STATES[$i]}"
    local title="${ALL_TASKS_TITLES[$i]}"
    local file="${ALL_TASKS_FILES[$i]}"

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
    if [[ $i -eq $SELECTED_IDX ]]; then
      cursor="${CYAN}▶${RESET} "
    fi

    case "$state_base" in
      in-progress)
        local stage="..."
        local fname
        fname=$(basename "$file" .md)
        local latest_log
        latest_log=$(ls -t "$LOG_DIR"/*"${fname}"* "$WORKTREE_BASE"/worker-*/.opencode/pipeline/logs/* 2>/dev/null | head -1 || true)
        if [[ -n "$latest_log" ]]; then
          stage=$(basename "$latest_log" .log | sed 's/^[0-9_]*//')
        fi
        buf_line "  ${cursor}  ${YELLOW}▸${RESET} $title ${DIM}[$stage]${RESET}"
        ;;
      done)
        local duration branch
        duration=$(grep -m1 '<!-- batch:' "$file" 2>/dev/null | sed 's/.*duration: \([0-9]*\)s.*/\1/' || echo "?")
        branch=$(grep -m1 '<!-- batch:' "$file" 2>/dev/null | sed 's/.*branch: \([^ ]*\) -->.*/\1/' || echo "?")
        if [[ "$duration" =~ ^[0-9]+$ ]]; then
          buf_line "  ${cursor}  ${GREEN}✓${RESET} $title ${DIM}($(format_duration "$duration"), $branch)${RESET}"
        else
          buf_line "  ${cursor}  ${GREEN}✓${RESET} $title"
        fi
        ;;
      failed)
        local duration
        duration=$(grep -m1 '<!-- batch:' "$file" 2>/dev/null | sed 's/.*duration: \([0-9]*\)s.*/\1/' || echo "?")
        if [[ "$duration" =~ ^[0-9]+$ ]]; then
          buf_line "  ${cursor}  ${RED}✗${RESET} $title ${DIM}($(format_duration "$duration"))${RESET}"
        else
          buf_line "  ${cursor}  ${RED}✗${RESET} $title"
        fi
        ;;
      todo)
        local prio="${state#todo:}"
        local prio_label=""
        if [[ "$prio" -gt 1 ]]; then
          prio_label=" ${MAGENTA}#${prio}${RESET}"
        fi
        buf_line "  ${cursor}  ${DIM}○${RESET} ${title}${prio_label}"
        ;;
    esac
  done

  buf_line ""
  buf_line "${DIM}$(hline)${RESET}"

  if [[ -n "$ACTION_MSG" ]]; then
    buf_line "  $ACTION_MSG"
    ACTION_MSG=""
  fi

  render_bottom_menu_buf
  buf_flush
}

# ---------------------------------------------------------------------------
# Context-aware bottom menu
# ---------------------------------------------------------------------------
render_bottom_menu_buf() {
  local state=""
  if [[ $ALL_TASKS_COUNT -gt 0 && $SELECTED_IDX -lt $ALL_TASKS_COUNT ]]; then
    state="${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}"
  fi

  local keys="  ${DIM}←/→ tabs  ↑/↓ select  Enter detail"

  case "$state" in
    in-progress)
      keys="$keys  ${WHITE}[x]${DIM} stop task"
      ;;
    failed)
      keys="$keys  ${WHITE}[f]${DIM} retry failed"
      ;;
    todo)
      keys="$keys  ${WHITE}[p]${DIM} priority+  ${WHITE}[d]${DIM} priority-"
      ;;
  esac

  keys="$keys  ${WHITE}[s]${DIM} start  ${WHITE}[k]${DIM} kill  ${WHITE}[q]${DIM} quit${RESET}"
  buf_line "$keys"
}

# ---------------------------------------------------------------------------
# Task detail view
# ---------------------------------------------------------------------------
render_task_detail_buf() {
  buf_line ""
  buf_line "  ${BOLD}Task Detail${RESET}  ${DIM}(Esc to go back)${RESET}"
  buf_line ""

  local title
  title=$(grep -m1 '^# ' "$DETAIL_FILE" 2>/dev/null | sed 's/^# //' || basename "$DETAIL_FILE" .md)
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

# ---------------------------------------------------------------------------
# Tab: Worker log
# ---------------------------------------------------------------------------
render_worker_tab() {
  local worker_id="$1"
  local worker_name="worker-${worker_id}"

  get_terminal_size
  buf_reset

  buf_line "${CYAN}${BOLD}  Pipeline Monitor${RESET}  ${DIM}$worker_name${RESET}  $(date '+%H:%M:%S')"
  buf_line "${DIM}$(hline)${RESET}"

  buf_line "$(render_tabs_str)"

  buf_line ""

  local wt_log_dir="$WORKTREE_BASE/$worker_name/.opencode/pipeline/logs"
  local log_file=""

  if [[ -d "$wt_log_dir" ]]; then
    log_file=$(ls -t "$wt_log_dir"/*.log 2>/dev/null | head -1 || true)
  fi

  if [[ -z "$log_file" ]]; then
    buf_line "  ${DIM}No active log found for $worker_name${RESET}"
    buf_line ""
    buf_line "  ${DIM}Log directory: $wt_log_dir${RESET}"
    buf_line ""
    buf_line "${DIM}$(hline)${RESET}"
    buf_line "  ${DIM}←/→ tabs  [s] start  [k] kill  [q] quit${RESET}"
    buf_flush
    return
  fi

  local agent_name
  agent_name=$(basename "$log_file" .log | sed 's/^[0-9_]*//')
  local log_size
  log_size=$(wc -c < "$log_file" | tr -d ' ')

  buf_line "  ${BOLD}Agent:${RESET} ${YELLOW}$agent_name${RESET}    ${BOLD}Log:${RESET} ${DIM}$(basename "$log_file")${RESET}    ${BOLD}Size:${RESET} ${DIM}$(( log_size / 1024 ))KB${RESET}"
  buf_line "${DIM}$(hline)${RESET}"

  local available_lines=$((TERM_ROWS - 8))
  [[ $available_lines -lt 5 ]] && available_lines=5

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
  done < <(tail -n "$available_lines" "$log_file" 2>/dev/null)

  buf_line ""
  buf_line "${DIM}$(hline)${RESET}"
  buf_line "  ${DIM}←/→ tabs  [s] start  [k] kill  [q] quit  (auto-refresh 3s)${RESET}"
  buf_flush
}

# ---------------------------------------------------------------------------
# Actions
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

    local branch_name
    branch_name=$(grep -m1 '<!-- batch:' "$f" 2>/dev/null | sed 's/.*branch: \([^ ]*\) -->.*/\1/' || true)
    if [[ -n "$branch_name" && "$branch_name" != "$(cat "$f")" ]]; then
      git -C "$REPO_ROOT" branch -D "$branch_name" 2>/dev/null || true
    fi

    sed '/^<!-- batch:.*-->$/d' "$f" > "$todo_dir/$(basename "$f")"
    rm -f "$f"
    count=$((count + 1))
  done

  if [[ $count -eq 0 ]]; then
    ACTION_MSG="${YELLOW}No failed tasks to retry${RESET}"
    return
  fi

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

  pkill -f 'pipeline-batch.sh' 2>/dev/null || true
  pkill -f 'opencode run --agent' 2>/dev/null || true
  ACTION_MSG="${RED}Killed batch processes${RESET}"
}

action_stop_task() {
  if [[ $ALL_TASKS_COUNT -eq 0 || $SELECTED_IDX -ge $ALL_TASKS_COUNT ]]; then
    return
  fi

  local state="${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}"
  if [[ "$state" != "in-progress" ]]; then
    ACTION_MSG="${YELLOW}Can only stop in-progress tasks${RESET}"
    return
  fi

  local file="${ALL_TASKS_FILES[$SELECTED_IDX]}"
  local title="${ALL_TASKS_TITLES[$SELECTED_IDX]}"

  # Move back to todo
  mkdir -p "$TASK_SOURCE/todo"
  sed '/^<!-- batch:.*-->$/d' "$file" > "$TASK_SOURCE/todo/$(basename "$file")"
  rm -f "$file"

  ACTION_MSG="${YELLOW}Stopped: ${title} → moved to todo${RESET}"
}

action_promote() {
  if [[ $ALL_TASKS_COUNT -eq 0 || $SELECTED_IDX -ge $ALL_TASKS_COUNT ]]; then
    return
  fi

  local state="${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}"
  if [[ "$state" != "todo" ]]; then
    ACTION_MSG="${YELLOW}Can only change priority of todo tasks${RESET}"
    return
  fi

  local file="${ALL_TASKS_FILES[$SELECTED_IDX]}"
  local current_prio
  current_prio=$(get_priority "$file")
  local new_prio=$((current_prio + 1))
  set_priority "$file" "$new_prio"
  ACTION_MSG="${MAGENTA}Priority → #${new_prio}${RESET}"
}

action_demote() {
  if [[ $ALL_TASKS_COUNT -eq 0 || $SELECTED_IDX -ge $ALL_TASKS_COUNT ]]; then
    return
  fi

  local state="${ALL_TASKS_STATES[$SELECTED_IDX]%%:*}"
  if [[ "$state" != "todo" ]]; then
    ACTION_MSG="${YELLOW}Can only change priority of todo tasks${RESET}"
    return
  fi

  local file="${ALL_TASKS_FILES[$SELECTED_IDX]}"
  local current_prio
  current_prio=$(get_priority "$file")
  local new_prio=$((current_prio > 1 ? current_prio - 1 : 1))
  set_priority "$file" "$new_prio"
  ACTION_MSG="${MAGENTA}Priority → #${new_prio}${RESET}"
}

ACTION_MSG=""

# ---------------------------------------------------------------------------
# Main render
# ---------------------------------------------------------------------------
render() {
  if [[ $CURRENT_TAB -eq 1 ]]; then
    render_overview
  else
    local workers=()
    local raw
    raw=$(detect_workers)
    [[ -n "$raw" ]] && read -ra workers <<< "$raw"
    local worker_idx=$((CURRENT_TAB - 1))
    if [[ ${#workers[@]} -gt 0 && $worker_idx -le ${#workers[@]} ]]; then
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
# Input handling — no subshell, global variable
# ---------------------------------------------------------------------------
LAST_KEY=""

read_key() {
  LAST_KEY="_timeout_"
  local key=""
  if read -rsn1 -t "$REFRESH_INTERVAL" key; then
    if [[ "$key" == $'\x1b' ]]; then
      # Read the rest of the escape sequence in one shot (2 bytes: [ + letter)
      local rest=""
      read -rsn2 -t 0.05 rest || true
      case "$rest" in
        "[A") LAST_KEY="UP" ;;
        "[B") LAST_KEY="DOWN" ;;
        "[C") LAST_KEY="RIGHT" ;;
        "[D") LAST_KEY="LEFT" ;;
        *)    LAST_KEY="ESC" ;;
      esac
      return
    fi
    # Enter key produces empty string
    if [[ -z "$key" ]]; then
      LAST_KEY="ENTER"
    else
      LAST_KEY="$key"
    fi
  fi
}

# ---------------------------------------------------------------------------
# Main loop
# ---------------------------------------------------------------------------
main() {
  # Use alternate screen buffer for clean entry/exit
  printf '\033[?1049h'
  tput civis 2>/dev/null || true
  trap 'tput cnorm 2>/dev/null; printf "\033[?1049l"; exit 0' EXIT INT TERM

  REFRESH_INTERVAL=3

  while true; do
    render

    read_key

    case "$LAST_KEY" in
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
      x|X)
        action_stop_task
        ;;
      p|P)
        action_promote
        ;;
      d|D)
        action_demote
        ;;
      UP)
        if [[ $SELECTED_IDX -gt 0 ]]; then
          SELECTED_IDX=$((SELECTED_IDX - 1))
        fi
        DETAIL_MODE=false
        ;;
      DOWN)
        if [[ $SELECTED_IDX -lt $((ALL_TASKS_COUNT - 1)) ]]; then
          SELECTED_IDX=$((SELECTED_IDX + 1))
        fi
        DETAIL_MODE=false
        ;;
      LEFT)
        if [[ $CURRENT_TAB -gt 1 ]]; then
          CURRENT_TAB=$((CURRENT_TAB - 1))
        fi
        DETAIL_MODE=false
        ;;
      RIGHT)
        if [[ $CURRENT_TAB -lt $MAX_TABS ]]; then
          CURRENT_TAB=$((CURRENT_TAB + 1))
        fi
        DETAIL_MODE=false
        ;;
      ESC|$'\x7f')  # Esc or Backspace
        DETAIL_MODE=false
        DETAIL_FILE=""
        ;;
      ENTER)
        if [[ $CURRENT_TAB -eq 1 && $ALL_TASKS_COUNT -gt 0 && $SELECTED_IDX -lt $ALL_TASKS_COUNT ]]; then
          DETAIL_MODE=true
          DETAIL_FILE="${ALL_TASKS_FILES[$SELECTED_IDX]}"
        fi
        ;;
      [1-9])
        if [[ "$LAST_KEY" -le $MAX_TABS ]]; then
          CURRENT_TAB=$LAST_KEY
          DETAIL_MODE=false
        fi
        ;;
    esac
  done
}

main
