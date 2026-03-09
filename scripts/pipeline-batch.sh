#!/usr/bin/env bash
#
# Batch runner for the multi-agent pipeline.
# Reads tasks from a text file or a tasks/ folder and runs the pipeline for each.
#
# Usage:
#   ./scripts/pipeline-batch.sh tasks.txt                     # one-line-per-task file
#   ./scripts/pipeline-batch.sh tasks/                        # folder-based kanban
#   ./scripts/pipeline-batch.sh --workers 2 tasks/            # 2 parallel workers
#   ./scripts/pipeline-batch.sh --no-stop-on-failure tasks.txt
#
# Folder mode: reads .md files from tasks/todo/, moves them through
# in-progress/ → done/ (or failed/) as they complete.
#
# Text file format (one task per line, empty lines and # comments ignored):
#   Add streaming support to A2A gateway
#   Implement agent marketplace search
#   # This is a comment
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCRIPT_DIR="$REPO_ROOT/scripts"
BATCH_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RESULTS_FILE="$REPO_ROOT/.opencode/pipeline/reports/batch_${BATCH_TIMESTAMP}.md"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Parse arguments
TASK_SOURCE=""
EXTRA_ARGS=""
STOP_ON_FAILURE=true
WEBHOOK_URL=""
WORKERS=1
WATCH_MODE=false
WATCH_INTERVAL=15  # seconds between polls for new tasks

while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-stop-on-failure)
      STOP_ON_FAILURE=false
      shift
      ;;
    --watch)
      WATCH_MODE=true
      shift
      ;;
    --watch-interval)
      WATCH_INTERVAL="$2"
      shift 2
      ;;
    --workers)
      WORKERS="$2"
      if ! [[ "$WORKERS" =~ ^[0-9]+$ ]] || [[ "$WORKERS" -lt 1 ]]; then
        echo -e "${RED}Error: --workers must be a positive integer${NC}"
        exit 1
      fi
      shift 2
      ;;
    --webhook)
      WEBHOOK_URL="$1 $2"
      EXTRA_ARGS="$EXTRA_ARGS $1 $2"
      shift 2
      ;;
    --*)
      EXTRA_ARGS="$EXTRA_ARGS $1"
      if [[ "$1" == "--from" || "$1" == "--only" || "$1" == "--branch" ]]; then
        EXTRA_ARGS="$EXTRA_ARGS $2"
        shift
      fi
      shift
      ;;
    *)
      if [[ -z "$TASK_SOURCE" ]]; then
        TASK_SOURCE="$1"
      fi
      shift
      ;;
  esac
done

# Default to tasks/ folder if it exists and no source was given
if [[ -z "$TASK_SOURCE" && -d "$REPO_ROOT/tasks" ]]; then
  TASK_SOURCE="$REPO_ROOT/tasks"
fi

if [[ -z "$TASK_SOURCE" ]]; then
  echo -e "${RED}Error: No task source provided${NC}"
  echo ""
  echo "Usage: ./scripts/pipeline-batch.sh <tasks-file-or-folder> [options]"
  echo ""
  echo "Two formats supported:"
  echo ""
  echo "  1) Text file (one task per line):"
  echo "     ./scripts/pipeline-batch.sh tasks.txt"
  echo ""
  echo "  2) Folder (one .md file per task in todo/):"
  echo "     ./scripts/pipeline-batch.sh tasks/"
  echo "     Files move: todo/ → in-progress/ → done/ (or failed/)"
  echo ""
  echo "Options:"
  echo "  --workers N           Run N tasks in parallel (default: 1, sequential)"
  echo "  --no-stop-on-failure  Continue to next task even if current fails"
  echo "  --watch               Keep running and pick up new tasks from todo/"
  echo "  --watch-interval N    Seconds between polls for new tasks (default: 15)"
  echo ""
  echo "Pipeline options are passed through to pipeline.sh:"
  echo "  --skip-architect    Skip the architect stage"
  echo "  --from <agent>      Start from a specific agent"
  echo "  --only <agent>      Run only a specific agent"
  echo "  --audit             Add auditor quality gate"
  echo "  --webhook <url>     Send notifications"
  echo "  --telegram          Send Telegram notifications"
  exit 1
fi

# ---------------------------------------------------------------------------
# Task loading — supports both file and folder formats
# ---------------------------------------------------------------------------

# Arrays populated by load_tasks_*:
#   TASK_NAMES[i]  — short name for display, logs, branch slug
#   TASK_FPATHS[i] — file path for --task-file (empty string = use TASK_NAMES as prompt)
TASK_NAMES=()
TASK_FPATHS=()
FOLDER_MODE=false

# Extract first # heading from a markdown file (fallback: filename without extension)
extract_title() {
  local file="$1"
  local title
  title=$(grep -m1 '^# ' "$file" 2>/dev/null | sed 's/^# //')
  if [[ -z "$title" ]]; then
    title=$(basename "$file" .md | sed 's/-/ /g')
  fi
  echo "$title"
}

load_tasks_from_file() {
  local file="$1"
  if [[ ! -f "$file" ]]; then
    echo -e "${RED}Error: Task file not found: ${file}${NC}"
    exit 1
  fi
  while IFS= read -r line; do
    TASK_NAMES+=("$line")
    TASK_FPATHS+=("")
  done < <(grep -v '^\s*$' "$file" | grep -v '^\s*#')
}

load_tasks_from_folder() {
  local folder="$1"
  local todo_dir="$folder/todo"

  if [[ ! -d "$todo_dir" ]]; then
    echo -e "${RED}Error: ${todo_dir}/ not found. Expected folder structure:${NC}"
    echo "  tasks/todo/       — tasks waiting to run"
    echo "  tasks/in-progress/ — currently running"
    echo "  tasks/done/       — completed"
    echo "  tasks/failed/     — failed"
    exit 1
  fi

  # Ensure lifecycle folders exist
  mkdir -p "$folder/in-progress" "$folder/done" "$folder/failed"

  # Read .md files sorted by name for predictable order
  local files=()
  while IFS= read -r -d '' f; do
    files+=("$f")
  done < <(find "$todo_dir" -maxdepth 1 -name '*.md' -print0 | sort -z)

  if [[ ${#files[@]} -eq 0 ]]; then
    echo -e "${YELLOW}No .md files found in ${todo_dir}/${NC}"
    exit 0
  fi

  FOLDER_MODE=true
  for f in "${files[@]}"; do
    local title
    title=$(extract_title "$f")
    TASK_NAMES+=("$title")
    TASK_FPATHS+=("$f")
  done
}

# Determine mode and load tasks
if [[ -f "$TASK_SOURCE" ]]; then
  load_tasks_from_file "$TASK_SOURCE"
elif [[ -d "$TASK_SOURCE" ]]; then
  load_tasks_from_folder "$TASK_SOURCE"
else
  echo -e "${RED}Error: ${TASK_SOURCE} is neither a file nor a directory${NC}"
  exit 1
fi

TOTAL=${#TASK_NAMES[@]}
if [[ $TOTAL -eq 0 ]]; then
  echo -e "${YELLOW}No tasks to run${NC}"
  exit 0
fi

mkdir -p "$(dirname "$RESULTS_FILE")"

PASSED=0
FAILED=0
STOPPED=false

# Save starting branch
ORIGINAL_BRANCH=$(git -C "$REPO_ROOT" branch --show-current)

# Display mode
if $FOLDER_MODE; then
  SOURCE_LABEL="${TASK_SOURCE}/todo/ (folder mode)"
else
  SOURCE_LABEL="${TASK_SOURCE} (file mode)"
fi

echo ""
echo -e "${CYAN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║${NC}     ${YELLOW}Batch Pipeline Runner v2${NC}                    ${CYAN}║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}Source:${NC}           ${SOURCE_LABEL}"
echo -e "${BLUE}Total tasks:${NC}      ${TOTAL}"
echo -e "${BLUE}Workers:${NC}          ${WORKERS}"
echo -e "${BLUE}Stop on failure:${NC}  ${STOP_ON_FAILURE}"
echo -e "${BLUE}Watch mode:${NC}       ${WATCH_MODE}"
echo -e "${BLUE}Extra args:${NC}       ${EXTRA_ARGS:-none}"
echo -e "${BLUE}Original branch:${NC}  ${ORIGINAL_BRANCH}"
echo -e "${BLUE}Results:${NC}          ${RESULTS_FILE}"
echo ""

# Log header
{
  echo "# Batch Pipeline Results"
  echo ""
  echo "- **Started**: $(date '+%Y-%m-%d %H:%M:%S')"
  echo "- **Source**: ${SOURCE_LABEL}"
  echo "- **Total tasks**: ${TOTAL}"
  echo "- **Workers**: ${WORKERS}"
  echo "- **Stop on failure**: ${STOP_ON_FAILURE}"
  echo ""
  echo "| # | Task | Status | Duration | Branch |"
  echo "|---|------|--------|----------|--------|"
} > "$RESULTS_FILE"

BATCH_START=$(date +%s)

# ---------------------------------------------------------------------------
# Helper: generate a slug from task description
# ---------------------------------------------------------------------------
task_slug() {
  echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/-/g' | sed 's/--*/-/g' | sed 's/^-//' | sed 's/-$//' | cut -c1-50
}

# ---------------------------------------------------------------------------
# Folder lifecycle: move task files between todo/in-progress/done/failed
# ---------------------------------------------------------------------------
move_to_in_progress() {
  local src="$1"
  local dest="${TASK_SOURCE}/in-progress/$(basename "$src")"
  mv "$src" "$dest"
  echo "$dest"
}

move_to_done() {
  local src="$1"
  local task_branch="$2"
  local duration="$3"
  local dest="${TASK_SOURCE}/done/$(basename "$src")"
  {
    echo "<!-- batch: ${BATCH_TIMESTAMP} | status: pass | duration: ${duration}s | branch: ${task_branch} -->"
    cat "$src"
  } > "$dest"
  rm -f "$src"
}

move_to_failed() {
  local src="$1"
  local task_branch="$2"
  local duration="$3"
  local dest="${TASK_SOURCE}/failed/$(basename "$src")"
  {
    echo "<!-- batch: ${BATCH_TIMESTAMP} | status: fail | duration: ${duration}s | branch: ${task_branch} -->"
    cat "$src"
  } > "$dest"
  rm -f "$src"
}

# ---------------------------------------------------------------------------
# Build pipeline.sh arguments for a task
# ---------------------------------------------------------------------------
pipeline_args() {
  local task_name="$1"
  local task_file="$2"  # empty string for text mode
  local task_branch="$3"

  local args="--branch $task_branch"

  if [[ -n "$task_file" ]]; then
    args="$args --task-file $task_file"
  fi

  echo "$args"
}

# ---------------------------------------------------------------------------
# Sequential mode (--workers 1, default)
# ---------------------------------------------------------------------------
run_sequential() {
  for i in "${!TASK_NAMES[@]}"; do
    local task="${TASK_NAMES[$i]}"
    local task_file="${TASK_FPATHS[$i]}"
    local task_num=$((i + 1))

    echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}Task ${task_num}/${TOTAL}:${NC} ${task}"
    echo -e "${CYAN}══════════════════════════════════════════════════${NC}"

    local start_time
    start_time=$(date +%s)
    local task_branch="pipeline/$(task_slug "$task")"

    # Folder mode: move to in-progress
    local active_file="$task_file"
    if $FOLDER_MODE && [[ -n "$task_file" ]]; then
      active_file=$(move_to_in_progress "$task_file")
    fi

    # Ensure we start from the original branch for each task
    git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true

    # Build pipeline command
    local pipeline_cmd
    if [[ -n "$active_file" ]]; then
      # shellcheck disable=SC2086
      pipeline_cmd="$SCRIPT_DIR/pipeline.sh --branch $task_branch --task-file $active_file $EXTRA_ARGS"
    else
      # shellcheck disable=SC2086
      pipeline_cmd="$SCRIPT_DIR/pipeline.sh --branch $task_branch $EXTRA_ARGS"
    fi

    # shellcheck disable=SC2086
    if [[ -n "$active_file" ]]; then
      # shellcheck disable=SC2086
      if "$SCRIPT_DIR/pipeline.sh" --branch "$task_branch" --task-file "$active_file" $EXTRA_ARGS; then
        local end_time duration
        end_time=$(date +%s)
        duration=$(( end_time - start_time ))
        PASSED=$((PASSED + 1))
        echo "| ${task_num} | ${task} | ✓ PASS | ${duration}s | \`${task_branch}\` |" >> "$RESULTS_FILE"
        echo -e "${GREEN}Task ${task_num} PASSED (${duration}s)${NC}"
        $FOLDER_MODE && move_to_done "$active_file" "$task_branch" "$duration"
      else
        local end_time duration
        end_time=$(date +%s)
        duration=$(( end_time - start_time ))
        FAILED=$((FAILED + 1))
        echo "| ${task_num} | ${task} | ✗ FAIL | ${duration}s | \`${task_branch}\` |" >> "$RESULTS_FILE"
        echo -e "${RED}Task ${task_num} FAILED (${duration}s)${NC}"
        $FOLDER_MODE && move_to_failed "$active_file" "$task_branch" "$duration"

        if [[ "$STOP_ON_FAILURE" == true ]]; then
          echo -e "${YELLOW}Stopping batch (--no-stop-on-failure to continue)${NC}"
          STOPPED=true
          for j in $(seq $((i + 1)) $((TOTAL - 1))); do
            echo "| $((j + 1)) | ${TASK_NAMES[$j]} | — SKIP | — | — |" >> "$RESULTS_FILE"
          done
          break
        fi
      fi
    else
      # Text mode — task as positional argument
      # shellcheck disable=SC2086
      if "$SCRIPT_DIR/pipeline.sh" --branch "$task_branch" $EXTRA_ARGS "$task"; then
        local end_time duration
        end_time=$(date +%s)
        duration=$(( end_time - start_time ))
        PASSED=$((PASSED + 1))
        echo "| ${task_num} | ${task} | ✓ PASS | ${duration}s | \`${task_branch}\` |" >> "$RESULTS_FILE"
        echo -e "${GREEN}Task ${task_num} PASSED (${duration}s)${NC}"
      else
        local end_time duration
        end_time=$(date +%s)
        duration=$(( end_time - start_time ))
        FAILED=$((FAILED + 1))
        echo "| ${task_num} | ${task} | ✗ FAIL | ${duration}s | \`${task_branch}\` |" >> "$RESULTS_FILE"
        echo -e "${RED}Task ${task_num} FAILED (${duration}s)${NC}"

        if [[ "$STOP_ON_FAILURE" == true ]]; then
          echo -e "${YELLOW}Stopping batch (--no-stop-on-failure to continue)${NC}"
          STOPPED=true
          for j in $(seq $((i + 1)) $((TOTAL - 1))); do
            echo "| $((j + 1)) | ${TASK_NAMES[$j]} | — SKIP | — | — |" >> "$RESULTS_FILE"
          done
          break
        fi
      fi
    fi

    git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true
    echo ""
  done
}

# ---------------------------------------------------------------------------
# Worktree dependency setup
#
# Git worktrees do NOT include .gitignored directories (vendor/, node_modules/,
# var/, .venv/). Symlink them from the main repo so agents can run tools like
# phpstan, codecept, openspec etc.
# ---------------------------------------------------------------------------
setup_worktree_deps() {
  local wt="$1"

  # Find vendor/, node_modules/, var/ directories in the main repo (max depth 3)
  # and create matching symlinks in the worktree
  while IFS= read -r dep_dir; do
    local rel_path="${dep_dir#"$REPO_ROOT"/}"
    local wt_parent="$wt/$(dirname "$rel_path")"
    local wt_target="$wt/$rel_path"

    # Skip if symlink already exists
    [[ -L "$wt_target" ]] && continue
    # Skip if directory already exists (shouldn't happen, but safety check)
    [[ -d "$wt_target" ]] && continue

    mkdir -p "$wt_parent"
    ln -s "$dep_dir" "$wt_target"
  done < <(find "$REPO_ROOT" -maxdepth 3 -type d \( -name vendor -o -name node_modules -o -name var -o -name '.venv' \) \
    -not -path '*/.opencode/*' -not -path '*/.git/*' 2>/dev/null)

  # Symlink .local/ if it exists (Docker volume mounts, local state)
  if [[ -d "$REPO_ROOT/.local" && ! -L "$wt/.local" ]]; then
    ln -s "$REPO_ROOT/.local" "$wt/.local"
  fi
}

# ---------------------------------------------------------------------------
# Parallel mode (--workers N where N > 1)
#
# Uses git worktrees so each worker has an isolated copy of the repo.
# A FIFO-based semaphore controls how many workers run concurrently.
# ---------------------------------------------------------------------------
run_parallel() {
  local WORKTREE_BASE="$REPO_ROOT/.opencode/pipeline/worktrees"
  local TASK_RESULT_DIR
  TASK_RESULT_DIR="$(mktemp -d)"

  # FIFO for worker pool semaphore
  local POOL_FIFO
  POOL_FIFO="$(mktemp -u)"
  mkfifo "$POOL_FIFO"

  # Open FIFO bidirectionally on fd 3 (prevents blocking on open)
  exec 3<>"$POOL_FIFO"

  # Cleanup function
  cleanup_parallel() {
    exec 3>&- 2>/dev/null || true
    rm -f "$POOL_FIFO"
    rm -rf "$TASK_RESULT_DIR"
    for ((w=1; w<=WORKERS; w++)); do
      local wt="$WORKTREE_BASE/worker-${w}"
      if [[ -d "$wt" ]]; then
        git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || true
      fi
    done
    rmdir "$WORKTREE_BASE" 2>/dev/null || true
  }
  trap cleanup_parallel EXIT

  if [[ "$STOP_ON_FAILURE" == true ]]; then
    echo -e "${YELLOW}Note: --stop-on-failure is ignored with --workers > 1${NC}"
  fi

  echo -e "${BLUE}Creating ${WORKERS} git worktrees...${NC}"
  mkdir -p "$WORKTREE_BASE"

  for ((w=1; w<=WORKERS; w++)); do
    local wt="$WORKTREE_BASE/worker-${w}"
    if [[ -d "$wt" ]]; then
      git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || true
    fi
    git -C "$REPO_ROOT" worktree add --detach "$wt" HEAD 2>/dev/null
    setup_worktree_deps "$wt"
    echo -e "  ${GREEN}worker-${w}${NC} → ${wt}"
    echo "worker-${w}" >&3
  done
  echo ""

  # Launch tasks
  local pids=()

  for i in "${!TASK_NAMES[@]}"; do
    local task="${TASK_NAMES[$i]}"
    local task_file="${TASK_FPATHS[$i]}"
    local task_num=$((i + 1))
    local task_branch="pipeline/$(task_slug "$task")"

    # Folder mode: move to in-progress before dispatching
    local active_file="$task_file"
    if $FOLDER_MODE && [[ -n "$task_file" ]]; then
      active_file=$(move_to_in_progress "$task_file")
    fi

    # Block until a worker slot is available
    local worker_id
    read -r worker_id <&3

    echo -e "${BLUE}[${worker_id}] Task ${task_num}/${TOTAL}:${NC} ${task}"

    (
      local wt="$WORKTREE_BASE/${worker_id}"
      local start_time
      start_time=$(date +%s)

      local pipeline_script="$wt/scripts/pipeline.sh"

      # For folder mode, the task file path is relative to REPO_ROOT
      # Copy the task file into the worktree so pipeline.sh can find it
      local wt_task_file=""
      if [[ -n "$active_file" ]]; then
        wt_task_file="$wt/.pipeline-task-${task_num}.md"
        cp "$active_file" "$wt_task_file"
      fi

      local exit_code=0
      if [[ -n "$wt_task_file" ]]; then
        # shellcheck disable=SC2086
        "$pipeline_script" --branch "$task_branch" --task-file "$wt_task_file" $EXTRA_ARGS \
          > "$TASK_RESULT_DIR/task-${task_num}.log" 2>&1 || exit_code=$?
      else
        # shellcheck disable=SC2086
        "$pipeline_script" --branch "$task_branch" $EXTRA_ARGS "$task" \
          > "$TASK_RESULT_DIR/task-${task_num}.log" 2>&1 || exit_code=$?
      fi

      rm -f "$wt_task_file"

      local end_time duration
      end_time=$(date +%s)
      duration=$(( end_time - start_time ))

      if [[ $exit_code -eq 0 ]]; then
        echo "PASS|${task_num}|${task}|${duration}|${task_branch}" > "$TASK_RESULT_DIR/task-${task_num}.result"
        echo -e "${GREEN}[${worker_id}] Task ${task_num} PASSED (${duration}s)${NC}"
        # Folder lifecycle — move to done
        if [[ -n "$active_file" && -f "$active_file" ]]; then
          move_to_done "$active_file" "$task_branch" "$duration"
        fi
      else
        echo "FAIL|${task_num}|${task}|${duration}|${task_branch}" > "$TASK_RESULT_DIR/task-${task_num}.result"
        echo -e "${RED}[${worker_id}] Task ${task_num} FAILED (${duration}s)${NC}"
        # Folder lifecycle — move to failed
        if [[ -n "$active_file" && -f "$active_file" ]]; then
          move_to_failed "$active_file" "$task_branch" "$duration"
        fi
      fi

      # Return worker to pool
      echo "$worker_id" >&3
    ) &
    pids+=($!)
  done

  # Wait for all background tasks
  echo -e "${BLUE}Waiting for all workers to finish...${NC}"
  for pid in "${pids[@]}"; do
    wait "$pid" 2>/dev/null || true
  done

  # Aggregate results in task order
  for ((t=1; t<=TOTAL; t++)); do
    local result_file="$TASK_RESULT_DIR/task-${t}.result"
    if [[ -f "$result_file" ]]; then
      local line
      line=$(cat "$result_file")
      local status task_num task_desc duration branch
      IFS='|' read -r status task_num task_desc duration branch <<< "$line"

      if [[ "$status" == "PASS" ]]; then
        PASSED=$((PASSED + 1))
        echo "| ${task_num} | ${task_desc} | ✓ PASS | ${duration}s | \`${branch}\` |" >> "$RESULTS_FILE"
      else
        FAILED=$((FAILED + 1))
        echo "| ${task_num} | ${task_desc} | ✗ FAIL | ${duration}s | \`${branch}\` |" >> "$RESULTS_FILE"
      fi
    else
      echo "| ${t} | ${TASK_NAMES[$((t-1))]} | — ERROR | — | — |" >> "$RESULTS_FILE"
      FAILED=$((FAILED + 1))
    fi
  done

  echo ""
  echo -e "${BLUE}Worker logs: ${TASK_RESULT_DIR}${NC}"
}

# ---------------------------------------------------------------------------
# Run one round of tasks
# ---------------------------------------------------------------------------
run_round() {
  if [[ $WORKERS -gt 1 ]]; then
    run_parallel
  else
    run_sequential
  fi
}

# ---------------------------------------------------------------------------
# Re-scan todo/ for new tasks (watch mode)
# ---------------------------------------------------------------------------
rescan_tasks() {
  TASK_NAMES=()
  TASK_FPATHS=()

  if [[ -d "$TASK_SOURCE" ]]; then
    local todo_dir="$TASK_SOURCE/todo"
    [[ -d "$todo_dir" ]] || return 1

    local files=()
    while IFS= read -r -d '' f; do
      files+=("$f")
    done < <(find "$todo_dir" -maxdepth 1 -name '*.md' -print0 | sort -z)

    [[ ${#files[@]} -eq 0 ]] && return 1

    FOLDER_MODE=true
    for f in "${files[@]}"; do
      local title
      title=$(extract_title "$f")
      TASK_NAMES+=("$title")
      TASK_FPATHS+=("$f")
    done
    TOTAL=${#TASK_NAMES[@]}
    return 0
  fi
  return 1
}

# ---------------------------------------------------------------------------
# Print summary for current round
# ---------------------------------------------------------------------------
print_summary() {
  local batch_end batch_duration
  batch_end=$(date +%s)
  batch_duration=$(( batch_end - BATCH_START ))

  {
    echo ""
    echo "## Summary"
    echo ""
    echo "- **Passed**: ${PASSED}/${TOTAL}"
    echo "- **Failed**: ${FAILED}/${TOTAL}"
    if $STOPPED; then
      echo "- **Skipped**: $((TOTAL - PASSED - FAILED))/${TOTAL}"
    fi
    echo "- **Workers**: ${WORKERS}"
    echo "- **Watch mode**: ${WATCH_MODE}"
    echo "- **Total duration**: ${batch_duration}s ($(( batch_duration / 60 )) min)"
    echo "- **Completed**: $(date '+%Y-%m-%d %H:%M:%S')"
  } >> "$RESULTS_FILE"

  echo ""
  echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
  echo -e "${YELLOW}Batch Results:${NC}"
  echo -e "  ${GREEN}Passed:${NC}   ${PASSED}/${TOTAL}"
  echo -e "  ${RED}Failed:${NC}   ${FAILED}/${TOTAL}"
  if $STOPPED; then
    echo -e "  ${YELLOW}Skipped:${NC}  $((TOTAL - PASSED - FAILED))/${TOTAL}"
  fi
  echo -e "  ${BLUE}Workers:${NC}  ${WORKERS}"
  echo -e "  ${BLUE}Duration:${NC} ${batch_duration}s ($(( batch_duration / 60 )) min)"
  echo -e "  ${BLUE}Report:${NC}   ${RESULTS_FILE}"
  echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# ---------------------------------------------------------------------------
# Main execution
# ---------------------------------------------------------------------------

# First round: run tasks loaded at startup
run_round
print_summary

if $WATCH_MODE && $FOLDER_MODE; then
  echo ""
  echo -e "${CYAN}Watch mode active — polling todo/ every ${WATCH_INTERVAL}s (Ctrl-C to stop)${NC}"

  while true; do
    sleep "$WATCH_INTERVAL"

    if rescan_tasks; then
      echo ""
      echo -e "${CYAN}══════════════════════════════════════════════════${NC}"
      echo -e "${YELLOW}New tasks detected: ${TOTAL}${NC}"
      echo -e "${CYAN}══════════════════════════════════════════════════${NC}"

      # Reset counters for new round
      PASSED=0
      FAILED=0
      STOPPED=false
      BATCH_START=$(date +%s)
      BATCH_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
      RESULTS_FILE="$REPO_ROOT/.opencode/pipeline/reports/batch_${BATCH_TIMESTAMP}.md"
      mkdir -p "$(dirname "$RESULTS_FILE")"

      # Write new report header
      {
        echo "# Batch Pipeline Results (watch round)"
        echo ""
        echo "- **Started**: $(date '+%Y-%m-%d %H:%M:%S')"
        echo "- **Source**: ${TASK_SOURCE}/todo/ (watch mode)"
        echo "- **Total tasks**: ${TOTAL}"
        echo "- **Workers**: ${WORKERS}"
        echo ""
        echo "| # | Task | Status | Duration | Branch |"
        echo "|---|------|--------|----------|--------|"
      } > "$RESULTS_FILE"

      run_round
      print_summary
    fi
  done
fi

# Return to original branch
git -C "$REPO_ROOT" checkout "$ORIGINAL_BRANCH" 2>/dev/null || true

[[ $FAILED -eq 0 ]] || exit 1
