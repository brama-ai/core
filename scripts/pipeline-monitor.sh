#!/usr/bin/env bash
#
# Interactive pipeline monitor with tab-based TUI.
#
# Usage:
#   ./scripts/pipeline-monitor.sh              # auto-detect tasks/ folder
#   ./scripts/pipeline-monitor.sh tasks/       # monitor specific tasks folder
#
# Tabs:
#   [1] Overview   — task statuses, progress bar, timing
#   [2] Logs       — latest pipeline log (always present)
#   [3] Worker 1   — live log tail for worker-1
#   ...
#
# Keys:
#   ←/→ or 1-9  Switch tabs
#   ↑/↓         Select task (Overview tab)
#   Enter       View selected task detail
#   Esc/Bksp    Back to task list
#   s           Start batch / start extra worker for selected task
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

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TASK_SOURCE="${1:-$REPO_ROOT/tasks}"
if [[ -d "$REPO_ROOT/.pipeline-worktrees" ]]; then
  WORKTREE_BASE="$REPO_ROOT/.pipeline-worktrees"
else
  WORKTREE_BASE="$REPO_ROOT/.opencode/pipeline/worktrees"
fi
LOG_DIR="$REPO_ROOT/.opencode/pipeline/logs"

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
WORKERS="${MONITOR_WORKERS:-2}"

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
    find "$dir" -maxdepth 1 -name '*.md' -not -name '.gitkeep' 2>/dev/null | wc -l | tr -d ' '
  else
    echo "0"
  fi
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
detect_workers() {
  local workers=() wcount=0
  if [[ -d "$WORKTREE_BASE" ]]; then
    for wt in "$WORKTREE_BASE"/worker-*; do
      [[ -d "$wt" ]] && { workers+=("$(basename "$wt")"); wcount=$((wcount + 1)); }
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
build_task_list() {
  ALL_TASKS_FILES=(); ALL_TASKS_TITLES=(); ALL_TASKS_STATES=()
  local dirs=("in-progress" "done" "failed")
  for d in "${dirs[@]}"; do
    if [[ -d "$TASK_SOURCE/$d" ]]; then
      while IFS= read -r f; do
        [[ -f "$f" ]] || continue
        ALL_TASKS_FILES+=("$f")
        ALL_TASKS_TITLES+=("$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)")
        ALL_TASKS_STATES+=("$d")
      done < <(find "$TASK_SOURCE/$d" -maxdepth 1 -name '*.md' 2>/dev/null | sort)
    fi
  done
  while IFS= read -r f; do
    [[ -f "$f" ]] || continue
    ALL_TASKS_FILES+=("$f")
    ALL_TASKS_TITLES+=("$(grep -m1 '^# ' "$f" 2>/dev/null | sed 's/^# //' || basename "$f" .md)")
    ALL_TASKS_STATES+=("todo:$(get_priority "$f")")
  done < <(list_todo_sorted)
  ALL_TASKS_COUNT=${#ALL_TASKS_FILES[@]}
  if [[ $SELECTED_IDX -ge $ALL_TASKS_COUNT ]]; then
    SELECTED_IDX=$((ALL_TASKS_COUNT > 0 ? ALL_TASKS_COUNT - 1 : 0))
  fi
}

# ── Tab bar ───────────────────────────────────────────────────────────
render_tabs_str() {
  local workers=()
  local raw; raw=$(detect_workers)
  [[ -n "$raw" ]] && read -ra workers <<< "$raw"
  ACTIVE_WORKER_COUNT=${#workers[@]}
  MAX_TABS=$((2 + ACTIVE_WORKER_COUNT))

  local out="  "
  # Tab 1: Overview
  if [[ $CURRENT_TAB -eq 1 ]]; then
    out+="${REV}${BOLD} 1:Overview ${RESET}"
  else
    out+="${DIM} 1:Overview ${RESET}"
  fi
  # Tab 2: Logs
  if [[ $CURRENT_TAB -eq 2 ]]; then
    out+="${REV}${BOLD} 2:Logs ${RESET}"
  else
    out+="${DIM} 2:Logs ${RESET}"
  fi
  # Tabs 3+: Workers
  local idx=3
  for w in "${workers[@]}"; do
    local task_hint; task_hint=$(get_worker_active_task "$w")
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

# ── Tab: Overview ─────────────────────────────────────────────────────
render_overview() {
  get_terminal_size
  buf_reset
  build_task_list

  local todo_count in_progress_count done_count failed_count
  todo_count=$(count_files "$TASK_SOURCE/todo")
  in_progress_count=$(count_files "$TASK_SOURCE/in-progress")
  done_count=$(count_files "$TASK_SOURCE/done")
  failed_count=$(count_files "$TASK_SOURCE/failed")
  local total=$((todo_count + in_progress_count + done_count + failed_count))
  local completed=$((done_count + failed_count))

  buf_line "${CYAN}${BOLD}  Pipeline Monitor${RESET}  $(date '+%H:%M:%S')"
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
      buf_line "  ${BOLD}Status:${RESET} ${YELLOW}Not running${RESET} — ${BOLD}${todo_count} tasks waiting${RESET}, press ${WHITE}[s]${RESET} to start"
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
        local fname; fname=$(basename "$file" .md)
        local latest_log; latest_log=$(ls -t "$LOG_DIR"/*"${fname}"* "$WORKTREE_BASE"/worker-*/.opencode/pipeline/logs/* 2>/dev/null | head -1 || true)
        [[ -n "$latest_log" ]] && stage=$(basename "$latest_log" .log | sed 's/^[0-9_]*//')
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
        fi ;;
      failed)
        local duration
        duration=$(grep -m1 '<!-- batch:' "$file" 2>/dev/null | sed 's/.*duration: \([0-9]*\)s.*/\1/' || echo "?")
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
    local log_size; log_size=$(wc -c < "$LOG_VIEW_FILE" | tr -d ' ')
    buf_line "  ${BOLD}Task Logs${RESET}  ${DIM}$(basename "$LOG_VIEW_FILE")  $(( log_size / 1024 ))KB  (q/Esc back)${RESET}"
    local available_lines=$((TERM_ROWS - 7))
    [[ $available_lines -lt 5 ]] && available_lines=5
    render_log_lines "$LOG_VIEW_FILE" "$available_lines"
  fi
}

find_task_log() {
  local file="$1"
  local fname; fname=$(basename "$file" .md)
  # Search in main log dir and worktree log dirs
  local log=""
  log=$(ls -t "$LOG_DIR"/*"${fname}"* "$WORKTREE_BASE"/worker-*/.opencode/pipeline/logs/*"${fname}"* 2>/dev/null | head -1 || true)
  # Fallback: try matching by task slug from title
  if [[ -z "$log" ]]; then
    local title; title=$(grep -m1 '^# ' "$file" 2>/dev/null | sed 's/^# //')
    local slug; slug=$(echo "$title" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g;s/--*/-/g;s/^-//;s/-$//' | cut -c1-50)
    [[ -n "$slug" ]] && log=$(ls -t "$LOG_DIR"/*"${slug}"* 2>/dev/null | head -1 || true)
  fi
  echo "$log"
}

# ── Tab: Logs (always present) ────────────────────────────────────────
render_logs_tab() {
  get_terminal_size
  buf_reset
  buf_line "$(render_tabs_str)  ${DIM}$(date '+%H:%M:%S')${RESET}"

  local log_file=""
  [[ -d "$LOG_DIR" ]] && log_file=$(ls -t "$LOG_DIR"/*.log 2>/dev/null | head -1 || true)

  if [[ -z "$log_file" ]]; then
    buf_line "  ${DIM}No log files found${RESET}"
    buf_line "  ${DIM}Log directory: $LOG_DIR${RESET}"
  else
    local agent_name; agent_name=$(basename "$log_file" .log | sed 's/^[0-9_]*//')
    local log_size; log_size=$(wc -c < "$log_file" | tr -d ' ')
    buf_line "  ${YELLOW}$agent_name${RESET}  ${DIM}$(basename "$log_file")  $(( log_size / 1024 ))KB${RESET}"
    local available_lines=$((TERM_ROWS - 4))
    [[ $available_lines -lt 5 ]] && available_lines=5
    render_log_lines "$log_file" "$available_lines"
  fi

  buf_line "  ${DIM}←/→ tabs  [s] start  [k] kill  [q] quit  (auto-refresh ${REFRESH_INTERVAL}s)${RESET}"
  buf_flush
}

# ── Tab: Worker log ───────────────────────────────────────────────────
render_worker_tab() {
  local worker_name="worker-${1}"
  get_terminal_size
  buf_reset
  buf_line "$(render_tabs_str)  ${DIM}$(date '+%H:%M:%S')${RESET}"

  local log_file=""
  local wt_log_dir="$WORKTREE_BASE/$worker_name/.opencode/pipeline/logs"
  [[ -d "$wt_log_dir" ]] && log_file=$(ls -t "$wt_log_dir"/*.log 2>/dev/null | head -1 || true)
  # Fallback to main log dir
  [[ -z "$log_file" && -d "$LOG_DIR" ]] && log_file=$(ls -t "$LOG_DIR"/*.log 2>/dev/null | head -1 || true)

  if [[ -z "$log_file" ]]; then
    buf_line "  ${DIM}No active log found for $worker_name${RESET}"
    buf_line "  ${DIM}Log directory: $wt_log_dir${RESET}"
  else
    local agent_name; agent_name=$(basename "$log_file" .log | sed 's/^[0-9_]*//')
    local log_size; log_size=$(wc -c < "$log_file" | tr -d ' ')
    buf_line "  ${YELLOW}$agent_name${RESET}  ${DIM}$(basename "$log_file")  $(( log_size / 1024 ))KB${RESET}"
    local available_lines=$((TERM_ROWS - 4))
    [[ $available_lines -lt 5 ]] && available_lines=5
    render_log_lines "$log_file" "$available_lines"
  fi

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
  caffeinate -s nohup "$REPO_ROOT/scripts/pipeline-batch.sh" \
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
  rm -rf "$REPO_ROOT/tasks/artifacts/${fname}" 2>/dev/null || true
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
    local artifacts_dir="$REPO_ROOT/tasks/artifacts"; mkdir -p "$artifacts_dir"
    [[ ! -L "$wt/tasks/artifacts" ]] && { mkdir -p "$wt/tasks"; ln -s "$artifacts_dir" "$wt/tasks/artifacts"; }
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
    fi
    rm -f "$active_file"
    git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || rm -rf "$wt"
  ) &
  ACTION_MSG="${GREEN}Started extra worker-${worker_num}: ${title}${RESET}"
}

# ── Main render dispatch ──────────────────────────────────────────────
render() {
  if [[ $CURRENT_TAB -eq 1 ]]; then
    render_overview
  elif [[ $CURRENT_TAB -eq 2 ]]; then
    render_logs_tab
  else
    local workers=()
    local raw; raw=$(detect_workers)
    [[ -n "$raw" ]] && read -ra workers <<< "$raw"
    local worker_idx=$((CURRENT_TAB - 2))
    if [[ ${#workers[@]} -gt 0 && $worker_idx -le ${#workers[@]} ]]; then
      local worker_num; worker_num=$(echo "${workers[$((worker_idx - 1))]}" | sed 's/worker-//')
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

  while true; do
    render
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
      r|R)        continue ;;
      s|S)        action_start ;;
      f|F)        action_retry_failed ;;
      k|K)        action_kill ;;
      x|X)        action_stop_task ;;
      +)          action_promote ;;
      -)          action_demote ;;
      d|D)        action_delete ;;
      a|A)        action_archive ;;
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
